#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Post-process rules2/*.md (jeux already-built files) to apply the same
OCR-cleanup regexes as the reussite builder. Operates in-place.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

OUT = Path(__file__).resolve().parents[1] / "rules2"

# Same OCR rules as build_rules2_reussites.py
OCR = [
    (r"\bTr[eé]fle", "Trèfle"),
    (r"\bt[eé]te\b", "tête"),
    (r"siécle", "siècle"), (r"\bsiecle\b", "siècle"),
    (r"\bpremiere\b", "première"), (r"\bdeuxieme\b", "deuxième"),
    (r"\btroisieme\b", "troisième"), (r"\bquatrieme\b", "quatrième"),
    (r"\bcinquieme\b", "cinquième"), (r"\bsixieme\b", "sixième"),
    (r"\bderniere\b", "dernière"),
    (r"premiéres?", "première"), (r"deuxiémes?", "deuxième"),
    (r"troisiémes?", "troisième"), (r"quatriémes?", "quatrième"),
    (r"cinquiémes?", "cinquième"), (r"septiémes?", "septième"),
    (r"sixiémes?", "sixième"), (r"huitiémes?", "huitième"),
    (r"neuviémes?", "neuvième"), (r"dixiémes?", "dixième"),
    (r"méme", "même"), (r"aprés", "après"), (r"étre", "être"),
    (r"trés", "très"), (r"éréal", "ère"), (r"prés", "près"),
    (r"arréte", "arrête"),
    (r"\bPon\b", "l'on"),
    (r"\bPo[nt] (?=peut|veut|d[eé]sire|sait|joue|est)", "l'on "),
    (r"frangaise", "française"), (r"frangais", "français"),
    (r"régles", "règles"), (r"régle", "règle"),
    (r"\boi\b", "où"), (r"\boti\b", "où"),
    (r"\baussit[6oô]t", "aussitôt"),
    (r"\bcommencant\b", "commençant"),
    (r"cotiteuse", "coûteuse"),
    (r"fagon", "façon"),
    (r"de fagon", "de façon"),
    (r"interêt", "intérêt"),
    (r"\bTréfle", "Trèfle"),
]

# Decorative layout fragments from jeux OCR
LAYOUT_DECOR = re.compile(
    r"(?:(?:H\}|Wh|Mh|Wb|Ny|Mb|V\sVE|V\sV\s+SHSOV|SHSOVSHSOV)"
    r"(?:\s+(?:H\}|Wh|Mh|Wb|Ny|Mb|V\sVE|V\sV\s+SHSOV|SHSOVSHSOV))*"
    r"(?:\s+\+|\.|\s)*\s*)"
)
# Pure layout bits (uppercase letters grouped) usually follow player counts
LAYOUT_BITS = re.compile(
    r"\s+(?:H\}|Wh|Mh|Wb|Ny|Mb|Ah|Mh\+?|Nh\+?|Vh\+?)\s*"
    r"(?:H\}|Wh|Mh|Wb|Ny|Mb|Ah|Nh|Vh)?\s*"
    r"(?:H\}|Wh|Mh|Wb|Ny|Mb|Ah|Nh|Vh)?"
)
# Page-number-only lines
PAGE_NUM_LINE = re.compile(r"(?m)^\s*\d{1,3}\s*$")
# Long symbol/digit decoration runs
SYMBOL_RUN = re.compile(r"[H\}\)\{NVA-Z0-9@\.]{12,}")
# Loose "V VE" or "V V " patches
V_VE = re.compile(r"\s+V\s+VE\b")
V_V重复 = re.compile(r"\bV\s+V\s+SHSOV[A-Z]*")
# " à à " doubling
A_A = re.compile(r"\bà à\b")
# Stray H}N or H} bits after player counts
H_BRACE = re.compile(r"\s*H\}[A-Z]*\s*")
# Pure letter runs of O/P/V that look like OCR of digit tables
OPV_RUN = re.compile(r"\s*\bO[PVO]{2,}\b\s*")
# "Nh" / "Ny" / "Wh" / "Mh" footer codes in headings
TITLE_CODE_SUFFIX = re.compile(
    r"(?m)^(# .{2,80}?)\s+(?:Nh|Ny|Wh|Mh|Wb|Ah|Mi?h|Nh\+|Ny\+|Mb)\s*$"
)
# Same idea for inline titles inside the body (uppercase title + code)
INLINE_TITLE_CODE = re.compile(
    r"(?m)^(LE|LA|LES|UN|UNE|L'|DES|DU|AU|AUX)\s+"
    r"([A-ZÀ-Ý][A-ZÀ-Ý \-']{2,40})\s+"
    r"(?:Nh|Ny|Wh|Mh|Wb|Ah|Nh\+|Ny\+|Mb)\b\s*$"
)
# Inline codes "Nh" / "Ny" etc. after a title with no leading "#"
INLINE_CODE = re.compile(r"\s+(?:Nh|Ny|Wh|Mh|Wb)\s+(?=[A-ZÀ-Ý«])")
# " + (X) + " ascii decorations next to titles
TITLE_PLUS_DECOR = re.compile(
    r"(?m)^((?:#[# ]*)?[A-ZÀ-Ý][^\n]{2,80}?)\s+\+\s+\([a-z]\)\s*\+"
)


def clean_md(text: str) -> str:
    # Drop page-number-only lines (rare in markdown)
    text = PAGE_NUM_LINE.sub("", text)
    # Apply OCR swaps
    for a, b in OCR:
        text = re.sub(a, b, text)
    # Layout fragments cleanup
    text = LAYOUT_DECOR.sub(" ", text)
    text = V_VE.sub("", text)
    text = V_V重复.sub("", text)
    text = H_BRACE.sub(" ", text)
    text = OPV_RUN.sub(" ", text)
    text = TITLE_CODE_SUFFIX.sub(r"\1", text)
    text = INLINE_TITLE_CODE.sub(r"\1 \2", text)
    text = INLINE_CODE.sub(" ", text)
    text = TITLE_PLUS_DECOR.sub(r"\1", text)
    # Strip "H}N" / "Wh / Mh" type faceplates
    text = re.sub(r"(?<![\w])\s*(?:H\}|Wh|Mh|Wb|Ny|Mb|Ah|Nh|Vh)\b", "", text)
    text = re.sub(r"\b(?:H\}|Wh|Mh|Wb|Ny|Mb|Ah|Nh|Vh)\s+", " ", text)
    text = A_A.sub("à", text)
    # Collapse spaces
    text = re.sub(r"[ \t]{2,}", " ", text)
    text = re.sub(r"\s+([,.;:])", r"\1", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text


def main():
    target = sys.argv[1] if len(sys.argv) > 1 else None
    files = sorted(p for p in OUT.glob("*.md")
                   if not p.name.startswith("_") and not p.name.startswith("2-"))
    if target:
        files = [p for p in files if target in p.stem]
    print(f"Cleaning {len(files)} files…")
    changed = 0
    for p in files:
        before = p.read_text(encoding="utf-8")
        after = clean_md(before)
        if after != before:
            p.write_text(after, encoding="utf-8")
            changed += 1
    print(f"Done. {changed}/{len(files)} files updated.")


if __name__ == "__main__":
    main()
