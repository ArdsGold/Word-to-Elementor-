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
    """Recognize major section headings across common roofing copy variants."""
    n = norm(s)
    if not n:
        return None

    # Closing is identified positionally by the parser after FAQ.
    # Do not classify CTA wording here; the heading can use any wording.

    # FAQ variants, including "Roof Leak Repair FAQs" and "FAQs About ...".
    if (
        n.startswith("frequently asked question")
        or n.startswith("faq")
        or n.startswith("faqs")
        or n.endswith(" faqs")
        or " frequently asked questions" in n
        or " faq" in n
    ):
        return "faq"

    # Process variants.
    if (
        "process" in n
        or "installation process" in n
        or n.endswith(" procedure")
        or n == "procedure"
        or n.startswith("our process")
    ):
        return "process"

    # Why variants.
    if (
        n.startswith("why choose")
        or n.startswith("why hire")
        or n.startswith("why do people")
        or n in {"why us", "why choose us"}
    ):
        return "why"

    # Services variants.
    if (
        "services" in n
        or n.endswith(" service")
        or n in {"services", "our services", "roofing services"}
    ) and not any(x in n for x in ("process", "faq", "question")):
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
    """Parse flexible roofing DOCX layouts into the fixed Elementor slots.

    The parser uses Heading 1 as the preferred major-section boundary.
    Services is also allowed to be Heading 2 because some source documents
    use an H2 for the Services heading while other documents use H1.

    The hero may contain an H2 subtitle, so an H2 containing the word
    "services" is NOT automatically treated as Services if it appears as
    hero copy before the actual Services boundary.
    """
    rows = read_rows(path)
    if not rows:
        raise ValueError("No readable paragraphs found.")

    o = Outline()
    o.title = rows[0][1]

    # ---- Find Services boundary ----
    # Find the Services heading structurally, not merely by wording.
    #
    # A CTA heading at the end can contain the word "Services", so choosing
    # the first H1 that matches "services" is unsafe. Instead, a Services
    # candidate must be followed by at least four item headings before the
    # next major H1. This also supports documents where Services is H2/H3.
    services_i = None

    for j in range(1, len(rows)):
        level, txt = rows[j]
        if level not in (1, 2, 3):
            continue
        if classify_section(txt) != "services":
            continue

        # Count headings belonging to this candidate's section until the
        # next H1. The fixed Elementor template requires four service items.
        item_count = 0
        for k in range(j + 1, len(rows)):
            next_level, next_txt = rows[k]
            if next_level == 1:
                break
            if next_level in (2, 3):
                item_count += 1

        if item_count >= MAX_ITEMS["services"]:
            services_i = j
            break

    if services_i is None:
        raise ValueError(
            "Could not find a structurally valid Services section after "
            "the page title."
        )

    # Hero = everything between page-title H1 and Services boundary.
    for level, txt in rows[1:services_i]:
        if txt:
            o.hero.append(txt)

    o.section_titles["services"] = rows[services_i][1]

    # ---- Find remaining major section boundaries ----
    # For sections after Services, require H1. This prevents an internal H2
    # such as "Professional Roof Repairs" from being mistaken for Process.
    expected = ["why", "process", "faq"]
    boundaries = {"services": services_i}
    search_from = services_i + 1

    # Identify named major sections by their H1 headings.
    for sec in expected:
        found = None
        for j in range(search_from, len(rows)):
            level, txt = rows[j]
            if level == 1 and classify_section(txt) == sec:
                found = j
                break
        if found is None:
            headings = [txt for level, txt in rows[services_i:] if level > 0]
            raise ValueError(
                f"Could not identify major section(s): {sec}. "
                f"Headings found after Services: " + " | ".join(headings)
            )
        boundaries[sec] = found
        search_from = found + 1

    # Closing is position-based: the first H1 after the FAQ section is
    # the closing/CTA heading, regardless of its wording.
    faq_i = boundaries["faq"]
    closing_i = None
    for j in range(faq_i + 1, len(rows)):
        level, txt = rows[j]
        if level == 1:
            closing_i = j
            break

    # Fallback for files where the closing heading is not H1:
    # after the six FAQ item headings, the next heading is closing.
    if closing_i is None:
        faq_item_headings = 0
        for j in range(faq_i + 1, len(rows)):
            level, txt = rows[j]
            if level > 0:
                faq_item_headings += 1
                if faq_item_headings > 6:
                    closing_i = j
                    break

    if closing_i is None:
        headings = [txt for level, txt in rows[faq_i:] if level > 0]
        raise ValueError(
            "Could not identify major section(s): closing. "
            "No heading was found after the FAQ items. "
            "Headings found after FAQ: " + " | ".join(headings)
        )

    boundaries["closing"] = closing_i

    # ---- Parse each bounded section ----
    section_order = ["services", "why", "process", "faq", "closing"]

    for idx, sec in enumerate(section_order):
        start_i = boundaries[sec]
        end_i = (
            boundaries[section_order[idx + 1]]
            if idx + 1 < len(section_order)
            else len(rows)
        )

        o.section_titles[sec] = rows[start_i][1]
        section_rows = rows[start_i + 1:end_i]

        if sec == "closing":
            for level, txt in section_rows:
                if txt:
                    o.closing.append(txt)
            continue

        current_item = None
        for level, txt in section_rows:
            if level > 0:
                current_item = Item(txt)
                o.items[sec].append(current_item)
            elif current_item is not None:
                current_item.bodies.append(txt)

    return o

def validate(o):
    """Validate constraints imposed by the Elementor template."""
    if not o.title:
        raise ValueError("HeroH1/page title is missing.")

    for sec, maximum in MAX_ITEMS.items():
        count = len(o.items[sec])
        if count > maximum:
            raise ValueError(
                f"{sec} contains {count} items; template allows {maximum}."
            )

    for sec in ("services", "why", "process", "faq", "closing"):
        if not o.section_titles[sec]:
            raise ValueError(f"Missing required major section: {sec}.")

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


# v3 change:
# Closing/CTA headings are detected by document position, not CTA wording:
# after the FAQ major section, the next heading is treated as the Closing
# heading. This prevents the parser from requiring phrase-specific CTA rules.
