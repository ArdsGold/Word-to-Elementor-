# Word to Elementor WF

WordPress plugin that fills a bundled Elementor page template from a `.docx` outline and creates a **draft** page.

## Install

1. Copy this folder to `wp-content/plugins/Word-to-Elementor-WF`.
2. Activate **Word to Elementor WF** (Elementor must be active).
3. Open **Word to Elementor WF** in the admin menu, upload a `.docx`, optionally set a page title, and create a draft.

## Word outline

Use Word heading styles (not just bold text):

- **Heading 1** — page title. An optional second Heading 1 is the hero subtitle.
- Intro paragraphs — hero body copy. If there is no second Heading 1, the first intro paragraph (or first sentence, when there is only one paragraph) is used as the subtitle.
- **Heading 2** — sections: Services, Why Choose Us, Process, FAQ, Closing
- **Heading 3** + a following paragraph — service cards, why-choose-us cards, process steps, or FAQ question/answer

The template uses the **first 4** services. Why Choose Us, Process, and FAQ each take up to **6** items. Process step numbers 1–6 and the “What You Can Expect” line stay as they are in the Elementor layout.
