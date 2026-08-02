#!/usr/bin/env python3
import hashlib
import json
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CATALOG = ROOT / "site/v3/catalog.json"
MANIFEST = ROOT / "assets/images/wikimedia.json"
OUTPUT = ROOT / "site/v3/images/games"
ALLOWED_LICENSES = {
    "CC0", "Public domain", "CC BY 1.0", "CC BY 2.0", "CC BY 2.5", "CC BY 3.0", "CC BY 4.0",
    "CC BY-SA 1.0", "CC BY-SA 2.0", "CC BY-SA 2.5", "CC BY-SA 3.0", "CC BY-SA 4.0",
}


def download(url: str, destination: Path) -> None:
    for delay in (0, 10, 30, 60, 120):
        time.sleep(delay)
        try:
            request = urllib.request.Request(url, headers={"User-Agent": "PKcards/1.0 (licensed image build)"})
            with urllib.request.urlopen(request, timeout=60) as response:
                destination.write_bytes(response.read())
            time.sleep(5)
            return
        except Exception:
            if delay == 120:
                raise


def source_url(image: dict) -> str:
    if "/thumb/" in image["thumb_url"]:
        return image["thumb_url"]
    filename = urllib.parse.quote(image["file"].removeprefix("File:"), safe="")
    width = int(image.get("width") or 500)
    thumbnail = max(size for size in (20, 40, 60, 120, 250, 330, 500, 960) if size < width)
    return f"https://commons.wikimedia.org/wiki/Special:Redirect/file/{filename}?width={thumbnail}"


def main() -> None:
    magick = shutil.which("magick")
    if not magick:
        raise SystemExit("ImageMagick is required")
    catalog = json.loads(CATALOG.read_text())
    manifest = json.loads(MANIFEST.read_text())["images"]
    expected = set(catalog["games"])
    if set(manifest) != expected:
        raise SystemExit("Wikimedia manifest must cover every canonical game exactly once")
    for slug, image in manifest.items():
        if image.get("license") not in ALLOWED_LICENSES:
            raise SystemExit(f"Unsupported license for {slug}: {image.get('license')}")
        if not all(image.get(field) for field in ("file", "author", "page_url", "thumb_url")):
            raise SystemExit(f"Incomplete attribution for {slug}")

    OUTPUT.mkdir(parents=True, exist_ok=True)
    force = "--force" in sys.argv
    built = {}
    cache = Path(tempfile.gettempdir()) / "pkcards-wikimedia-cache"
    cache.mkdir(exist_ok=True)
    for index, (slug, image) in enumerate(manifest.items(), 1):
        output = OUTPUT / f"{slug}.webp"
        if output.exists() and not force:
            continue
        source = source_url(image)
        key = (source, image.get("gravity", "center"))
        if key in built:
            shutil.copyfile(built[key], output)
        else:
            raw = cache / (hashlib.sha256(source.encode()).hexdigest() + ".image")
            if not raw.exists():
                download(source, raw)
            subprocess.run([
                magick, str(raw), "-auto-orient", "-resize", "640x400^", "-gravity",
                image.get("gravity", "center"), "-extent", "640x400", "-strip", "-quality", "82", str(output),
            ], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            built[key] = output
        print(f"{index:03d}/251 {slug}", flush=True)

    generated = {path.stem for path in OUTPUT.glob("*.webp")}
    if generated != expected:
        raise SystemExit(f"Expected exactly {len(expected)} game images, found {len(generated)}")
    print(f"Generated {len(generated)} licensed game images")


if __name__ == "__main__":
    main()
