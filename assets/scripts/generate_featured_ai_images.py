#!/usr/bin/env python3
import shutil
import subprocess
import sys
import tempfile
import urllib.parse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / "site/v3/images/games"

PROMPTS = {
    "all-fives": (
        "Dark moody still life photography, five antique brass weights arranged in ascending size on a weathered dark oak table, single dramatic spotlight from above creating deep shadows, warm amber and bronze tones, chiaroscuro lighting, fine art editorial photography, sophisticated, cinematic, no text",
        11,
    ),
    "all-fours": (
        "Dark architectural fine art photography, four weathered ancient stone pillars standing in thick morning fog, dramatic shadows, minimalist composition, cold blue-grey stone tones, cinematic atmosphere, editorial style, sophisticated, no text",
        22,
    ),
    "allo-jack": (
        "Vintage black bakelite rotary telephone sitting on a dark walnut desk, single beam of light from window blind, deep film noir shadows, 1940s detective atmosphere, cinematic photography, warm tungsten tones, editorial style, sophisticated, no text",
        33,
    ),
    "anandis": (
        "Dark detective desk scene, single playing card lying face down under a brass magnifying glass, dramatic spotlight, deep shadows surrounding, film noir cinematography, moody mysterious atmosphere, warm amber tones, fine art editorial photography, no text",
        44,
    ),
    "animals": (
        "Dark moody nocturnal forest scene, wild animal tracks pressed into moonlit snow, thick mist drifting between dark bare trees, cool blue moonlight tones, cinematic nature photography, atmospheric, editorial style, sophisticated, no text",
        55,
    ),
    "basra": (
        "Ancient Egyptian temple ruins at golden hour dusk, weathered sandstone columns casting long dramatic shadows, warm desert sunset light, archaeological fine art photography, cinematic atmosphere, deep amber and terracotta tones, editorial style, no text",
        66,
    ),
    "big-three": (
        "Three massive dark stone monoliths rising from misty barren landscape, heavy dramatic fog rolling between them, minimalist composition, cold desaturated grey tones, fine art landscape photography, cinematic atmosphere, awe-inspiring scale, editorial style, no text",
        77,
    ),
    "black-jack-banquier": (
        "Dark moody casino table close-up, weathered green felt surface, two playing cards face up showing ace of spades and king of hearts, dramatic single spotlight overhead, deep surrounding shadows, film noir cinematography, warm golden tones, fine art editorial photography, no text",
        88,
    ),
    "blanche-neige": (
        "Dark gothic fairytale still life, single glossy red apple resting on a tarnished antique silver mirror, dramatic chiaroscuro lighting, deep velvety shadows, moody ominous atmosphere, fine art photography, rich crimson and dark tones, editorial style, no text",
        99,
    ),
    "bonjour-monsieur": (
        "Two elegant vintage men's fedora hats placed facing each other on a dark polished marble surface, minimalist symmetric composition, warm sepia and bronze tones, dramatic side lighting casting long shadows, fine art still life photography, sophisticated editorial style, no text",
        111,
    ),
}


def main():
    OUTPUT.mkdir(parents=True, exist_ok=True)
    raw_dir = Path(tempfile.gettempdir()) / "pkcards-ai-raw"
    raw_dir.mkdir(exist_ok=True)
    for slug, (prompt, seed) in PROMPTS.items():
        webp = OUTPUT / f"{slug}.webp"
        if webp.exists() and "--force" not in sys.argv:
            print(f"skip {slug} (exists)")
            continue
        encoded = urllib.parse.quote(prompt, safe="")
        url = f"https://image.pollinations.ai/prompt/{encoded}?width=640&height=400&nologo=true&seed={seed}&model=flux"
        raw = raw_dir / f"{slug}.jpg"
        print(f"generating {slug}...")
        subprocess.run(["curl", "-fsS", "-o", str(raw), url], check=True)
        magick = shutil.which("magick")
        subprocess.run([magick, str(raw), "-resize", "640x400!", "-strip", "-quality", "82", str(webp)], check=True)
        print(f"  done {webp.name}")
    print(f"Generated {len(PROMPTS)} AI game images")


if __name__ == "__main__":
    main()
