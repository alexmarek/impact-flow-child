#!/usr/bin/env python3
"""Assemble the Home page block markup from the approved design source.

The approved design file remains the source of truth for the CSS and for the
two sections that are kept as code-managed raw markup (the style-card grid and
the enquiry form). This script splices those two pieces into the hand-authored
block template so the large, data-driven markup is never copied by hand.

Usage:
    python3 tools/2026-09-20-build-homepage-blocks.py
"""

from pathlib import Path
import re

THEME = Path(__file__).resolve().parent.parent
DESIGN = THEME / "assets/design/2026-09-20-impact-flow-homepage-v2-1.html"
TEMPLATE = THEME / "assets/content/2026-09-20-homepage-blocks.template.html"
OUTPUT = THEME / "assets/content/2026-09-20-homepage-blocks.html"

design = DESIGN.read_text(encoding="utf-8")


def extract(pattern: str, label: str) -> str:
    match = re.search(pattern, design, re.DOTALL)
    if not match:
        raise SystemExit(f"Could not find {label} in {DESIGN.name}")
    return match.group(0)


styles_match = re.search(
    r'<section class="wrap styles" id="styles">(.*?)</section>', design, re.DOTALL
)
if not styles_match:
    raise SystemExit("Could not find the styles section in the design file")

form_match = re.search(r'(<form id="enquiry">.*?</form>)', design, re.DOTALL)
if not form_match:
    raise SystemExit("Could not find the enquiry form in the design file")

styles_block = (
    '<!-- wp:group {"tagName":"section","anchor":"styles","className":"wrap styles","layout":{"type":"default"}} -->\n'
    '<section id="styles" class="wp-block-group wrap styles">\n'
    "<!-- wp:html -->\n"
    f"{styles_match.group(1)}\n"
    "<!-- /wp:html -->\n"
    "</section>\n"
    "<!-- /wp:group -->"
)

form_block = "<!-- wp:html -->\n" + form_match.group(1) + "\n<!-- /wp:html -->"

content = TEMPLATE.read_text(encoding="utf-8")
content = content.replace("{{STYLES_SECTION}}", styles_block)
content = content.replace("{{ENQUIRY_FORM}}", form_block)
content = content.rstrip() + "\n"

OUTPUT.write_text(content, encoding="utf-8")
print(f"Wrote {OUTPUT.relative_to(THEME)} ({len(content)} bytes)")