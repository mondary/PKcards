#!/usr/bin/env python3
import hashlib
import json
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CATALOG = ROOT / "site/v3/catalog.json"
CARDS = sorted((ROOT / "assets/cards").glob("*.png"))
OUTPUT = ROOT / "site/v3/images/games"

PALETTES = [
    ("#f2eadf", "#e63b2e", "#171717", "#f4b942"),
    ("#dce8f5", "#2357d9", "#101b35", "#ef5da8"),
    ("#e8edcf", "#a6ce39", "#18382b", "#ff7043"),
    ("#f4dfe8", "#d72d79", "#25203f", "#f2a900"),
    ("#efe1cc", "#c65d35", "#4c1f2d", "#2d8074"),
    ("#d9eeee", "#00a6a6", "#102c35", "#ffcb47"),
    ("#e7e0f2", "#7457c8", "#321d4f", "#ef6f61"),
    ("#eee9dc", "#d49a24", "#162e3c", "#d94f4f"),
]


def generate(slug: str, force: bool) -> None:
    output = OUTPUT / f"{slug}.webp"
    if output.exists() and not force:
        return
    digest = hashlib.sha256(slug.encode()).digest()
    paper, accent, ink, spark = PALETTES[digest[0] % len(PALETTES)]
    diagonal = 230 + digest[1] % 180
    draw = (
        f"polygon 0,0 {diagonal},0 {diagonal - 150},400 0,400 "
        f"circle {510 + digest[2] % 70},{60 + digest[3] % 80} {650 + digest[2] % 70},{60 + digest[3] % 80}"
    )
    lines = " ".join(f"line {x},0 {x - 180},400" for x in range(70, 790, 72))
    command = [
        shutil.which("magick") or "magick", "-size", "640x400", f"xc:{paper}",
        "-fill", accent, "-draw", draw,
        "-stroke", spark, "-strokewidth", "3", "-fill", "none", "-draw", lines,
        "-fill", ink, "-stroke", "none", "-draw", f"rectangle 0,{350 + digest[4] % 22} 640,400",
    ]
    count = 3 + digest[5] % 2
    for index in range(count):
        card = CARDS[digest[6 + index] % len(CARDS)]
        angle = -20 + digest[12 + index] % 41
        x = 52 + index * 128 + digest[18 + index] % 36
        y = 68 + digest[24 + index] % 72
        command += [
            "(", str(card), "-resize", "148x214", "-bordercolor", ink, "-border", "2",
            "-background", "none", "-rotate", str(angle), ")",
            "-geometry", f"+{x}+{y}", "-composite",
        ]
    command += ["-strip", "-quality", "78", str(output)]
    subprocess.run(command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def main() -> None:
    if not shutil.which("magick"):
        raise SystemExit("ImageMagick is required")
    catalog = json.loads(CATALOG.read_text())
    OUTPUT.mkdir(parents=True, exist_ok=True)
    force = "--force" in sys.argv
    for slug in catalog["games"]:
        generate(slug, force)
    generated = list(OUTPUT.glob("*.webp"))
    if len(generated) != len(catalog["games"]):
        raise SystemExit(f"Expected {len(catalog['games'])} images, found {len(generated)}")
    print(f"Generated {len(generated)} distinct game images")


if __name__ == "__main__":
    main()
