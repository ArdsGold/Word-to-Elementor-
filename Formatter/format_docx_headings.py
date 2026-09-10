#!/usr/bin/env python3
"""
Backward-compatible Word-to-Elementor DOCX formatter.

Run with no arguments:
    python format_docx_headings.py

It automatically processes every .docx in ./unformatted and writes:
    ./formatted/<name> - formatted.docx

Content mapping follows AutomationTestTemplate2.0.json:
HeroH1, HeroP,
Section2H2 + Section2Content1..4,
Section3H2 + Section3H2Subtitle + Section3Content1..6,
Section4H2 + Section4Content1H3/Desc..6,
Section5H2 + Section5Content,
Section6H2 + Section6P.
"""
from __future__ import annotations
import argparse
import json
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path
from docx import Document

BASE_DIR = Path(__file__).resolve().parent
INPUT_DIR = BASE_DIR / "unformatted"
OUTPUT_DIR = BASE_DIR / "formatted"
SAMPLE_DIRS = (BASE_DIR / "sample formats", BASE_DIR / "sample_formats", BASE_DIR)
SAMPLE_NAMES = ("Sample Format.docx", "sample format.docx")
SECTIONS = ("services", "why", "process", "faq", "closing")
MAX_ITEMS = {"services": 4, "why": 6, "process": 6, "faq": 6}

@dataclass
class Item:
    title: str
    bodies: list[str] = field(default_factory=list)

@dataclass
class Outline:
    title: str = ""
    hero: list[str] = field(default_factory=list)
    section_titles: dict[str, str] = field(default_factory=dict)
    section_subtitles: dict[str, str] = field(default_factory=dict)
    items: dict[str, list[Item]] = field(default_factory=dict)
    closing: list[str] = field(default_factory=list)
    def __post_init__(self):
        for s in SECTIONS:
            self.section_titles.setdefault(s, "")
            self.section_subtitles.setdefault(s, "")
            self.items.setdefault(s, [])

def ptext(p):
    return "".join(r.text or "" for r in p.runs).strip()

def hlevel(p):
    style = p.style
    name = (style.name or "") if style else ""
    m = re.search(r"heading\s*([1-3])", name, re.I)
    if m:
        return int(m.group(1))
    sid = getattr(style, "style_id", "") if style else ""
    m = re.search(r"heading\s*([1-3])", sid or "", re.I)
    if m:
        return int(m.group(1))
    return int(sid) if sid in {"1", "2", "3"} else 0

def norm(s):
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9]+", " ", s.lower())).strip()

def classify_section(s):
    """Recognize section headings by meaning, not one exact phrase.

    The source DOCX files use different but equivalent headings, e.g.
    "Emergency Roof Repair Services" and "Our Emergency Roof Repair Process".
    Section boundaries must therefore be detected from keywords.
    """
    n = norm(s)
    if not n:
        return None

    # Closing sections must be checked before FAQ because a closing H1 can
    # otherwise be swallowed as another FAQ question.
    if (
        "closing" in n
        or n.startswith("upgrade your")
        or n.startswith("protect your")
        or n.startswith("contact us")
        or n.startswith("get started")
        or n.startswith("schedule your")
        or n.startswith("call us")
    ):
        return "closing"

    if (
        n.startswith("frequently asked questions")
        or n in {"faq", "faqs"}
    ):
        return "faq"

    if "process" in n or "installation process" in n:
        return "process"

    if (
        n.startswith("why choose")
        or n.startswith("why do people")
        or n in {"why us", "why choose us"}
    ):
        return "why"

    # Any heading whose main purpose is to introduce a collection of
    # services is a Services section, regardless of the leading adjective.
    if "services" in n and not any(x in n for x in ("process", "faq", "question")):
        return "services"

    return None

def read_rows(path):
    d = Document(str(path))
    rows = [(hlevel(p), ptext(p)) for p in d.paragraphs if ptext(p)]
    if not rows:
        raise ValueError(f"No readable paragraphs in {path}")
    return rows

def collect_body(rows, i):
    out = []
    while i < len(rows) and rows[i][0] == 0:
        out.append(rows[i][1])
        i += 1
    return out, i

def parse_outline(path):
    rows = read_rows(path)
    o = Outline()
    i = 0

    # HeroH1
    o.title = rows[i][1]
    i += 1

    # AutomationTestTemplate2.0 has HeroH1 + HeroP only. A heading immediately
    # following the H1 is therefore treated as hero copy, not a service item.
    if i < len(rows) and rows[i][0] in (1, 2) and not classify_section(rows[i][1]):
        o.hero.append(rows[i][1])
        i += 1

    b, i = collect_body(rows, i)
    o.hero.extend(b)

    current = None

    while i < len(rows):
        level, text = rows[i]
        sec = classify_section(text) if level in (1, 2) else None

        if sec:
            current = sec
            o.section_titles[sec] = text
            i += 1
            continue

        if current is None:
            i += 1
            continue

        # Section 6: all following content is closing copy.
        if current == "closing":
            o.closing.append(text)
            i += 1
            continue

        # Section 5: every heading is an FAQ question; following body is answer.
        if current == "faq":
            if level > 0:
                item = Item(text)
                i += 1
                b, i = collect_body(rows, i)
                item.bodies.extend(b)
                o.items[current].append(item)
            else:
                if o.items[current]:
                    o.items[current][-1].bodies.append(text)
                i += 1
            continue

        # Section 3 has an optional subtitle slot, but the source format
        # uses Heading 2 entries as the actual six "Why Choose Us" items.
        # Therefore, never consume the first heading as a subtitle merely
        # because another heading follows it. An optional subtitle can be
        # supplied explicitly later; by default this slot stays empty.

        # Services, Why, Process: heading + following body = item.
        if level > 0:
            item = Item(text)
            i += 1
            b, i = collect_body(rows, i)
            item.bodies.extend(b)
            o.items[current].append(item)
        else:
            if o.items[current]:
                o.items[current][-1].bodies.append(text)
            i += 1

    return o

def validate(o):
    if not o.title:
        raise ValueError("HeroH1/page title is missing.")
    for sec, maximum in MAX_ITEMS.items():
        if len(o.items[sec]) > maximum:
            raise ValueError(f"{sec} contains {len(o.items[sec])} items; template allows {maximum}.")
        for num, item in enumerate(o.items[sec], 1):
            if not item.bodies:
                raise ValueError(f"{sec} item {num} ({item.title!r}) has no description/body.")
    if o.closing and not o.section_titles["closing"]:
        raise ValueError("Closing copy exists without Section6H2.")

def clear_body(d):
    body = d.element.body
    sect = body.find("{http://schemas.openxmlformats.org/wordprocessingml/2006/main}sectPr")
    for child in list(body):
        if child is not sect:
            body.remove(child)

def add(d, style, value):
    p = d.add_paragraph(value)
    p.style = style

def write_docx(o, sample, output):
    d = Document(str(sample))
    clear_body(d)

    add(d, "Heading 1", o.title)
    for x in o.hero:
        add(d, "Normal", x)

    # Section 2
    if o.section_titles["services"]:
        add(d, "Heading 2", o.section_titles["services"])
        for x in o.items["services"]:
            add(d, "Heading 3", x.title)
            for b in x.bodies:
                add(d, "Normal", b)

    # Section 3
    if o.section_titles["why"]:
        add(d, "Heading 2", o.section_titles["why"])
        if o.section_subtitles["why"]:
            add(d, "Heading 3", o.section_subtitles["why"])
        for x in o.items["why"]:
            add(d, "Heading 3", x.title)
            for b in x.bodies:
                add(d, "Normal", b)

    # Section 4
    if o.section_titles["process"]:
        add(d, "Heading 2", o.section_titles["process"])
        for x in o.items["process"]:
            add(d, "Heading 3", x.title)
            for b in x.bodies:
                add(d, "Normal", b)

    # Section 5
    if o.section_titles["faq"]:
        add(d, "Heading 2", o.section_titles["faq"])
        for x in o.items["faq"]:
            add(d, "Heading 3", x.title)
            for b in x.bodies:
                add(d, "Normal", b)

    # Section 6
    if o.section_titles["closing"]:
        add(d, "Heading 2", o.section_titles["closing"])
        for b in o.closing:
            add(d, "Normal", b)

    output.parent.mkdir(parents=True, exist_ok=True)
    d.save(str(output))

def mapping(o):
    return {
        "HeroH1": o.title,
        "HeroP": "\n".join(o.hero),
        "Section2H2": o.section_titles["services"],
        "Section2Content": [
            {"data-customid": f"Section2Content{i}", "title": x.title, "description": "\n".join(x.bodies)}
            for i, x in enumerate(o.items["services"], 1)
        ],
        "Section3H2": o.section_titles["why"],
        "Section3H2Subtitle": o.section_subtitles["why"],
        "Section3Content": [
            {"data-customid": f"Section3Content{i}", "title": x.title, "description": "\n".join(x.bodies)}
            for i, x in enumerate(o.items["why"], 1)
        ],
        "Section4H2": o.section_titles["process"],
        "Section4Content": [
            {"h3_slot": f"Section4Content{i}H3", "description_slot": f"Section4Content{i}Desc",
             "title": x.title, "description": "\n".join(x.bodies)}
            for i, x in enumerate(o.items["process"], 1)
        ],
        "Section5H2": o.section_titles["faq"],
        "Section5Content": [
            {"question": x.title, "answer": "\n".join(x.bodies)}
            for x in o.items["faq"]
        ],
        "Section6H2": o.section_titles["closing"],
        "Section6P": "\n".join(o.closing),
    }

def find_sample():
    for directory in SAMPLE_DIRS:
        for name in SAMPLE_NAMES:
            candidate = directory / name
            if candidate.is_file():
                return candidate
    for directory in SAMPLE_DIRS:
        if directory.is_dir():
            for candidate in sorted(directory.glob("*.docx")):
                if not candidate.name.startswith("~$") and candidate.parent not in {INPUT_DIR, OUTPUT_DIR}:
                    return candidate
    return None

def parse_args(argv):
    ap = argparse.ArgumentParser(description="Format DOCX files for the Word-to-Elementor template.")
    ap.add_argument("inputs", nargs="*", type=Path)
    ap.add_argument("--sample", type=Path, default=None)
    ap.add_argument("--output-dir", type=Path, default=OUTPUT_DIR)
    ap.add_argument("--output", type=Path, default=None)
    ap.add_argument("--template-json", type=Path, default=None)
    ap.add_argument("--mapping-json", action="store_true")
    return ap.parse_args(argv)

def main(argv=None):
    a = parse_args(argv if argv is not None else sys.argv[1:])
    inputs = list(a.inputs) or sorted(
        p for p in INPUT_DIR.glob("*.docx") if not p.name.startswith("~$")
    ) if INPUT_DIR.is_dir() else []

    if not inputs:
        print(f"No .docx files found in {INPUT_DIR}", file=sys.stderr)
        return 1

    sample = a.sample or find_sample()
    if sample is None or not sample.is_file():
        print("Sample DOCX not found. Use --sample \"path\\to\\Sample Format.docx\".", file=sys.stderr)
        return 1

    failures = 0
    for source in inputs:
        if not source.is_file():
            print(f"Skip missing file: {source}", file=sys.stderr)
            failures += 1
            continue

        output = a.output if len(inputs) == 1 and a.output else a.output_dir / f"{source.stem} - formatted.docx"

        try:
            outline = parse_outline(source)
            validate(outline)
            write_docx(outline, sample, output)

            print(f"Wrote: {output}")
            print(f"  HeroH1: {outline.title!r}")
            print(f"  HeroP: {len(outline.hero)} paragraph(s)")
            print(f"  Section2Content: {len(outline.items['services'])}")
            print(f"  Section3Content: {len(outline.items['why'])}")
            print(f"  Section4Content: {len(outline.items['process'])}")
            print(f"  Section5Content: {len(outline.items['faq'])}")
            print(f"  Section6P: {len(outline.closing)} paragraph(s)")

            if a.mapping_json:
                print(json.dumps(mapping(outline), ensure_ascii=False, indent=2))

        except Exception as exc:
            print(f"Failed {source}: {exc}", file=sys.stderr)
            failures += 1

    return 1 if failures else 0

if __name__ == "__main__":
    raise SystemExit(main())
