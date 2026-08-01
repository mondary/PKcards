#!/usr/bin/env python3
import shutil
import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / "site/v3/images/games"


def card(x, y, angle, rank, suit, color="#151515", scale=1):
    suits = {
        "♠": '<path d="M0-34C-12-16-31-7-31 11c0 17 20 25 31 11-2 14-8 23-17 29h34C8 45 2 36 0 22c11 14 31 6 31-11C31-7 12-16 0-34Z"/>',
        "♥": '<path d="M0 34-28 5C-48-17-20-42 0-20 20-42 48-17 28 5Z"/>',
        "♣": '<path d="M0-29a18 18 0 1 1-8 34A18 18 0 1 1-5 20c-1 12-7 23-16 31h42C12 43 6 32 5 20A18 18 0 1 1 8 5 18 18 0 1 1 0-29Z"/>',
        "♦": '<path d="M0-38 29 0 0 38-29 0Z"/>',
        "★": '<path d="m0-38 9 27 29 1-23 17 8 28L0 19-23 35l8-28-23-17 29-1Z"/>',
    }
    return f'''<g transform="translate({x} {y}) rotate({angle}) scale({scale})" filter="url(#shadow)">
      <rect x="-55" y="-78" width="110" height="156" rx="10" fill="#fffdf7" stroke="#151515" stroke-width="4"/>
      <text x="-39" y="-42" class="rank" fill="{color}">{rank}</text>
      <g transform="translate(0 12)" fill="{color}">{suits[suit]}</g>
    </g>'''


def svg(bg, accent, label, body):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400">
    <defs>
      <filter id="shadow" x="-40%" y="-40%" width="180%" height="190%"><feDropShadow dx="7" dy="9" stdDeviation="5" flood-color="#000" flood-opacity=".28"/></filter>
      <pattern id="grain" width="18" height="18" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="#fff" opacity=".08"/><circle cx="14" cy="9" r=".8" fill="#111" opacity=".09"/></pattern>
      <style>.rank{{font-family:Helvetica;font-size:34px;font-weight:bold}}.suit{{font-family:Helvetica;font-size:58px;font-weight:bold}}.micro{{font-family:Helvetica;font-size:15px;font-weight:bold;letter-spacing:2px}}.display{{font-family:Helvetica;font-size:70px;font-weight:bold;letter-spacing:-5px}}</style>
    </defs>
    <rect width="640" height="400" fill="{bg}"/><rect width="640" height="400" fill="url(#grain)"/>
    <rect x="0" y="0" width="14" height="400" fill="{accent}"/>
    <text x="38" y="42" class="micro" fill="{accent}">{label}</text>
    {body}
    </svg>'''


SCENES = {
    "all-fives": svg("#f2e9da", "#e33b2f", "FIVE / HIGH · LOW · JACK · GAME", f'''
      <circle cx="320" cy="205" r="132" fill="#e33b2f"/><circle cx="320" cy="205" r="82" fill="#f2c84b"/>
      {card(205, 210, -24, "5", "♠")}{card(260, 190, -12, "5", "♥", "#c51f2b")}
      {card(320, 180, 0, "5", "♣")}{card(380, 190, 12, "5", "♦", "#c51f2b")}{card(435, 210, 24, "5", "★")}
    '''),
    "all-fours": svg("#173f36", "#f3c84b", "FOUR CORNERS / FOUR WAYS", f'''
      <path d="M320 62 478 200 320 338 162 200Z" fill="#f3c84b" stroke="#fff5dc" stroke-width="5"/>
      <circle cx="320" cy="200" r="45" fill="#173f36"/><text x="320" y="224" class="display" text-anchor="middle" fill="#fff5dc">4</text>
      {card(190, 120, -12, "4", "♠", scale=.8)}{card(450, 120, 12, "4", "♥", "#c51f2b", .8)}
      {card(190, 286, 12, "4", "♣", scale=.8)}{card(450, 286, -12, "4", "♦", "#c51f2b", .8)}
    '''),
    "allo-jack": svg("#cce4f2", "#1859a9", "ALLO JACK / REPONDS VITE", f'''
      <path d="M90 105h260a28 28 0 0 1 28 28v82a28 28 0 0 1-28 28H208l-54 48 14-48H90a28 28 0 0 1-28-28v-82a28 28 0 0 1 28-28Z" fill="#fffdf7" stroke="#151515" stroke-width="5"/>
      <text x="116" y="181" class="micro" fill="#1859a9">ALLO JACK!</text>
      <g filter="url(#shadow)"><rect x="410" y="74" width="152" height="228" rx="14" fill="#fffdf7" stroke="#151515" stroke-width="5"/><text x="526" y="124" class="rank" fill="#c51f2b">J</text><path d="M486 246 446 204c-29-33 12-70 40-37 28-33 69 4 40 37Z" fill="#c51f2b"/></g>
      <path d="M425 82c35-26 76-18 98 7l-31 34c-12 13-28 8-38-1l-34 36c10 14 25 19 38 6l35-34c27 25 31 68 4 97-53 56-151-38-98-94Z" fill="#f3c84b" stroke="#151515" stroke-width="5"/>
    '''),
    "anandis": svg("#211c35", "#c99af0", "ANANDIS / UNE CARTE MANQUE", f'''
      <g opacity=".82">{''.join(card(92+i*62, 108, (i-3)*2, r, "♠", scale=.42) for i, r in enumerate(["A","J","Q","K","A","J","Q","K"]))}</g>
      <g transform="translate(320 221) rotate(-4)" filter="url(#shadow)"><rect x="-78" y="-110" width="156" height="220" rx="14" fill="#c99af0" stroke="#f7efdf" stroke-width="5"/><path d="M-58-88 58 88M58-88-58 88" stroke="#211c35" stroke-width="14" opacity=".45"/><text x="0" y="30" class="display" text-anchor="middle" fill="#f7efdf">?</text></g>
      <circle cx="410" cy="237" r="70" fill="none" stroke="#f3c84b" stroke-width="12"/><path d="m458 286 74 68" stroke="#f3c84b" stroke-width="18" stroke-linecap="round"/>
    '''),
    "animals": svg("#f5d76e", "#253f2f", "ANIMALS / CRIE SON NOM", f'''
      <path d="M136 292V155l50-56 48 58 50-58 48 56v137Z" fill="#fff6df" stroke="#253f2f" stroke-width="6"/>
      <circle cx="210" cy="207" r="10" fill="#253f2f"/><circle cx="270" cy="207" r="10" fill="#253f2f"/><path d="m224 235 16 12 16-12M240 247v24" fill="none" stroke="#253f2f" stroke-width="7" stroke-linecap="round"/>
      <path d="M366 92h205v86H442l-43 35 10-35h-43Z" fill="#e9543d" stroke="#253f2f" stroke-width="5"/><text x="392" y="147" class="micro" fill="#fff">MIAOU! WOUF!</text>
      {card(408, 285, -10, "8", "♥", "#c51f2b", .72)}{card(510, 280, 11, "8", "♠", scale=.72)}
    '''),
    "basra": svg("#0e5e67", "#e9bc4b", "BASRA / VIDE LA TABLE", f'''
      <path d="M50 330V130h70V87h400v43h70v200" fill="none" stroke="#e9bc4b" stroke-width="11"/>
      <path d="M85 330V170h470v160" fill="#efe2c3" opacity=".22"/>
      <path d="M238 246h34m-17-17v34M374 235h40m-40 22h40" stroke="#e9bc4b" stroke-width="8" stroke-linecap="round"/>
      {card(174, 292, -13, "4", "♣", scale=.65)}{card(275, 292, 5, "5", "♥", "#c51f2b", .65)}{card(458, 230, 8, "9", "♦", "#c51f2b", .9)}
      <path d="M345 263c35-52 69-61 105-39" fill="none" stroke="#e9bc4b" stroke-width="8" marker-end="url(#arrow)"/>
    '''),
    "big-three": svg("#dce8f4", "#d93a31", "BIG THREE / LE 3 EST AU SOMMET", f'''
      <path d="M40 330 180 115 320 330Z" fill="#5674ba"/><path d="M180 115 137 182l43-18 33 18Z" fill="#fff"/>
      <path d="M210 330 365 70 530 330Z" fill="#334f94"/><path d="M365 70 315 154l50-22 41 22Z" fill="#fff"/>
      <path d="M400 330 525 135 635 330Z" fill="#1e386f"/>
      {card(180, 244, -12, "3", "♣", scale=.64)}{card(365, 205, 0, "3", "♥", "#c51f2b", .78)}{card(528, 260, 12, "3", "♠", scale=.58)}
      <circle cx="560" cy="78" r="43" fill="#d93a31"/><text x="560" y="99" class="display" text-anchor="middle" fill="#fff">3</text>
    '''),
    "black-jack-banquier": svg("#101a18", "#d4a93d", "BLACK JACK / TABLE 21", f'''
      <path d="M-30 340Q320 80 670 340" fill="#174a3b" stroke="#d4a93d" stroke-width="9"/>
      <text x="282" y="225" class="display" fill="#d4a93d">2</text><text x="340" y="225" class="display" fill="#d4a93d">1</text>
      <g transform="translate(220 218) rotate(-14)"><rect x="-57" y="-80" width="114" height="160" rx="10" fill="#fffdf7" stroke="#151515" stroke-width="4"/><text x="-39" y="-42" class="rank">A</text><path d="M0-22C-12-6-31 3-31 20c0 17 20 25 31 11-2 14-8 23-17 29h34C8 54 2 45 0 31c11 14 31 6 31-11C31 3 12-6 0-22Z" fill="#151515"/></g>
      <g transform="translate(420 218) rotate(14)"><rect x="-57" y="-80" width="114" height="160" rx="10" fill="#fffdf7" stroke="#151515" stroke-width="4"/><text x="-39" y="-42" class="rank" fill="#c51f2b">K</text><path d="M0 45-28 16C-48-6-20-31 0-9 20-31 48-6 28 16Z" fill="#c51f2b"/></g>
      <g transform="translate(220 218) rotate(-14)"><rect x="-57" y="-80" width="114" height="160" rx="10" fill="#fffdf7" stroke="#151515" stroke-width="4"/><text x="-39" y="-42" class="rank">A</text><path d="M0-22C-12-6-31 3-31 20c0 17 20 25 31 11-2 14-8 23-17 29h34C8 54 2 45 0 31c11 14 31 6 31-11C31 3 12-6 0-22Z" fill="#151515"/></g>
      <g fill="none" stroke="#d4a93d" stroke-width="7"><circle cx="100" cy="310" r="39"/><circle cx="540" cy="310" r="39"/><circle cx="585" cy="325" r="34"/></g>
    '''),
    "blanche-neige": svg("#dcecf3", "#a9182b", "BLANCHE-NEIGE / MIROIR MAGIQUE", f'''
      <ellipse cx="320" cy="202" rx="138" ry="164" fill="#b9d7e3" stroke="#d6aa46" stroke-width="16"/>
      <ellipse cx="320" cy="202" rx="112" ry="138" fill="#edf7f8" stroke="#fff" stroke-width="5"/>
      <path d="M320 102c-57 35-73 100-33 164 12 20 29 36 33 39 4-3 21-19 33-39 40-64 24-129-33-164Z" fill="#a9182b"/>
      <path d="M320 106c17-28 47-29 66-17-26 5-43 17-54 36Z" fill="#315d36"/>
      <g fill="#fff" opacity=".9">{''.join(f'<circle cx="{70+i*82}" cy="{72+(i%2)*230}" r="6"/>' for i in range(7))}</g>
      {card(515, 232, 13, "Q", "♥", "#c51f2b", .7)}
    '''),
    "bonjour-monsieur": svg("#efe4d3", "#713c83", "BONJOUR MONSIEUR / BONJOUR MADAME", f'''
      <path d="M0 72h320v328H0Z" fill="#cadcf0"/><path d="M320 72h320v328H320Z" fill="#f0cbd6"/>
      {card(190, 225, -8, "K", "♠", scale=1.05)}{card(450, 225, 8, "Q", "♥", "#c51f2b", 1.05)}
      <path d="M42 86h216v70H128l-35 28 8-28H42Z" fill="#fff" stroke="#151515" stroke-width="4"/><text x="66" y="130" class="micro" fill="#151515">BONJOUR MONSIEUR</text>
      <path d="M375 88h228v70H476l-36 28 8-28h-73Z" fill="#fff" stroke="#151515" stroke-width="4"/><text x="395" y="132" class="micro" fill="#a9182b">BONJOUR MADAME</text>
    '''),
}


def main():
    magick = shutil.which("magick")
    if not magick:
        raise SystemExit("ImageMagick is required")
    for slug, source in SCENES.items():
        with tempfile.NamedTemporaryFile("w", suffix=".svg", encoding="utf-8") as svg_file:
            svg_file.write(source)
            svg_file.flush()
            subprocess.run([magick, "-font", "/System/Library/Fonts/Helvetica.ttc", svg_file.name, "-resize", "640x400!", "-strip", "-quality", "82", str(OUTPUT / f"{slug}.webp")], check=True)
    print(f"Generated {len(SCENES)} dedicated game images")


if __name__ == "__main__":
    main()
