#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Build rules2 for multiplayer card games from Garraud 1984.
- Real games only (réussites deferred, Type will be "Réussite" later)
- One page illustration per game in rules2/images/
"""
from __future__ import annotations

import json
import re
import shutil
from collections import OrderedDict
from pathlib import Path

import fitz

ROOT = Path(__file__).resolve().parents[1]
SECTIONS = Path("/tmp/garraud_sections")
JEUX_PATH = Path("/tmp/garraud_jeux.json")
REUSSITES_PATH = Path("/tmp/garraud_reussites.json")
PDF_PATH = ROOT / "benchmark" / "isbn_2724225910 (Unknown) (z-library.sk, 1lib.sk, z-lib.sk).pdf"
OUT = ROOT / "rules2"
IMG = OUT / "images"

OCR = [
    (r"céte", "côte"),
    (r"frangaise", "française"),
    (r"frangais", "français"),
    (r"siécle", "siècle"),
    (r"premiéres?", "premières"),
    (r"deuxiéme", "deuxième"),
    (r"troisiéme", "troisième"),
    (r"quatriéme", "quatrième"),
    (r"cinquiéme", "cinquième"),
    (r"septiéme", "septième"),
    (r"régles", "règles"),
    (r"régle", "règle"),
    (r"méme", "même"),
    (r"aprés", "après"),
    (r"étre", "être"),
    (r"trés", "très"),
    (r"intérét", "intérêt"),
    (r"enchéres", "enchères"),
    (r"enchére", "enchère"),
    (r"particuliére", "particulière"),
    (r"got de", "goût de"),
    (r"caractére", "caractère"),
    (r"\boi\b", "où"),
    (r"\boti\b", "où"),
    (r"grace", "grâce"),
    (r"Coeur", "Cœur"),
    (r"coeur", "cœur"),
    (r"Cceur", "Cœur"),
    (r"I’on", "l'on"),
    (r"I'on", "l'on"),
    (r"I’", "l'"),
    (r"I'", "l'"),
    (r"\|’", "l'"),
    (r"\|'", "l'"),
    (r" 4 ", " à "),
    (r"^4 ", "à "),
    (r"4a ", "à "),
    (r"4 partir", "à partir"),
    (r"cing ", "cinq "),
    (r"Cing ", "Cinq "),
    (r"commengant", "commençant"),
    (r"facons", "façons"),
    (r"enléve", "enlève"),
    (r"considére", "considère"),
    (r"entrainer", "entraîner"),
    (r"cotiteuse", "coûteuse"),
    (r"achéte", "achète"),
    (r"bitches", "bûches"),
    (r"Bacarra", "Baccara"),
    (r"Punité", "l'unité"),
    (r"ltalie", "Italie"),
    (r"Etats-Unis", "États-Unis"),
    (r"fagon", "façon"),
    (r"jusqu’a", "jusqu'à"),
    (r"jusqu'a", "jusqu'à"),
    (r"Quiils", "Qu'ils"),
    (r"premières de", "première de"),
    (r"Pun des", "l'un des"),
    (r"II ", "Il "),
    (r"I] ", "Il "),
    (r"de fagon", "de façon"),
]
DECOR = re.compile(
    r"(?:POV|DOV|BOV|B0V|POP|DBO|DSP|ESO|SSO|SHO|GSO|VSS|VED|EOY|HOV|ADO|APY|ESDOV|YOVA)[A-Z0-9@®©]*",
    re.I,
)
FIELD_RE = re.compile(
    r"(Nombre de joueurs|Cartes|Matériel|Valeur des cartes|Ordre des cartes|"
    r"Ordre hiérarchique des couleurs|But du jeu|Tableau|Chance de réussite|"
    r"Annonces \(ou combinaisons\)|Annonces|Particularités|Particularites|Temps moyen)\s*:\s*",
    re.I,
)


def clean(s: str) -> str:
    if not s:
        return ""
    s = DECOR.sub("", s)
    s = re.sub(r"[P@V]{3,}", "", s)
    s = re.sub(r"(?m)^[POV@ESDOBHGFE0-9\s\|\~\(\)\,\.]{12,}$", "", s)
    s = re.sub(r"(\w)-\n(\w)", r"\1\2", s)
    for a, b in OCR:
        s = re.sub(a, b, s)
    s = re.sub(r"Dame Valet 10[0-9,\s]+", "Dame, Valet, 10, 9, 8, 7, 6, 5, 4, 3, 2", s)
    s = re.sub(r"(?<!\n)\n(?!\n)", " ", s)
    s = re.sub(r"[ \t]+", " ", s)
    s = re.sub(r"\b[VY,]+\s*\([a-z]\)\s*[~\-]\s*\([a-z]\)\b", "", s)
    s = re.sub(r"\s{2,}", " ", s)
    s = re.sub(r"\s+([,.;:])", r"\1", s)
    return s.strip()


def nice_title(raw: str) -> str:
    t = re.sub(r"\s+", " ", raw.strip())
    t = re.sub(r"\s+(Wh|Mh|Wb)$", "", t)
    letters = [c for c in t if c.isalpha()]
    if letters and sum(1 for c in letters if c.isupper()) / len(letters) >= 0.7:
        small = {
            "DE",
            "DU",
            "DES",
            "ET",
            "A",
            "À",
            "AU",
            "AUX",
            "LA",
            "LE",
            "LES",
            "OU",
            "EN",
            "SUR",
            "D",
            "L",
            "AVEC",
            "SANS",
            "PAR",
            "POUR",
        }
        words = []
        for i, p in enumerate(t.replace("'", "\u2019").split(" ")):
            if "\u2019" in p:
                a, b = p.split("\u2019", 1)
                words.append(
                    (a[:1].upper() + a[1:].lower())
                    + "\u2019"
                    + (b[:1].upper() + b[1:].lower() if b else "")
                )
            elif i > 0 and p.upper() in small:
                words.append(p.lower())
            else:
                words.append(p[:1].upper() + p[1:].lower() if p else p)
        t = " ".join(words)
    return t


def reslug(title: str) -> str:
    s = title.lower().replace("\u2019", "'").replace("'", "")
    for a, b in [
        ("é", "e"),
        ("è", "e"),
        ("ê", "e"),
        ("ë", "e"),
        ("à", "a"),
        ("â", "a"),
        ("ä", "a"),
        ("ô", "o"),
        ("ö", "o"),
        ("ù", "u"),
        ("û", "u"),
        ("ü", "u"),
        ("î", "i"),
        ("ï", "i"),
        ("ç", "c"),
        ("œ", "oe"),
    ]:
        s = s.replace(a, b)
    s = re.sub(r"^(le|la|les|un|une)[\s-]+", "", s)
    s = re.sub(r"^l(?=[aeiouy])", "", s)
    s = re.sub(r"[^a-z0-9]+", "-", s).strip("-")
    return re.sub(r"-+", "-", s) or "jeu"


def extract_fields(text: str) -> dict:
    fields = {}
    matches = list(FIELD_RE.finditer(text))
    for i, m in enumerate(matches):
        key = m.group(1).lower()
        start = m.end()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
        rest = text[start:end]
        dm = re.search(r"D[eé]roulement de la (?:partie|r[eé]ussite)", rest, re.I)
        if dm:
            rest = rest[: dm.start()]
        val = clean(rest)
        if len(val) > 8:
            fields[key] = val
    return fields


def get_procedure(raw: str):
    m = re.search(r"D[eé]roulement de la partie\s*(.*)", raw, re.I | re.S)
    if not m:
        m = re.search(r"D[eé]roulement de la r[eé]ussite\s*(.*)", raw, re.I | re.S)
    if not m:
        return clean(raw), None, None
    body = m.group(1)
    variants = conseils = None
    vm = re.search(r"(?i)\bVARIANTE[S]?\b\s*(.*)", body, re.S)
    if vm:
        variants = clean(vm.group(1))
        body = body[: vm.start()]
    cm = re.search(r"(?i)\bConseils?\b\s*:?\s*(.*)", body, re.S)
    if cm:
        conseils = clean(cm.group(1))
        body = body[: cm.start()]
    return clean(body), variants, conseils


def get_intro(raw: str) -> str:
    m = re.search(
        r"(Nombre de joueurs|Cartes\s*:|Valeur des cartes|But du jeu|Tableau\s*:|Ordre des cartes|Matériel\s*:|D[eé]roulement)",
        raw,
        re.I,
    )
    if not m:
        return ""
    intro = re.sub(r"^[^\n]+\n?", "", raw[: m.start()], count=1)
    intro = clean(intro)
    return intro if len(intro) > 40 else ""


def detect_type(title: str, text: str) -> str:
    blob = (title + " " + text[:900]).lower()
    types = []
    if re.search(
        r"\bpli|lev[eé]e|atout|fournir la couleur|manille|belote|bridge|whist|piquet",
        blob,
    ):
        types.append("Jeu de plis")
    if re.search(
        r"combinaison|brelan|carr[eé]|s[eé]quence|canasta|rami|m[eé]lange|suite", blob
    ):
        types.append("Combinaisons")
    if re.search(r"ench[eè]re|mise|pot|banquier|ponte|jeton|cave", blob):
        types.append("Enchères")
    if re.search(r"d[eé]fausse|se d[eé]barrasser|se d[eé]faire|pouilleux|nigaud", blob):
        types.append("Défausse")
    if re.search(r"m[eé]moire|m[eé]moriser", blob):
        types.append("Mémoire")
    if re.search(r"hasard|casino|baccara|faro|pharaon|roulette|banque", blob):
        types.append("Hasard")
    if re.search(r"poker|stud|draw", blob):
        if "Combinaisons" not in types:
            types.append("Combinaisons")
        if "Enchères" not in types:
            types.append("Enchères")
    if not types:
        types = ["Mixte"]
    out = []
    for x in types:
        if x not in out:
            out.append(x)
    return ", ".join(out)


def detect_difficulty(text: str) -> str:
    if re.search(
        r"(?i)(bridge|b[eé]sigue|piquet|hombre|aluette|skat|tarot|canasta)", text[:400]
    ):
        return "Difficile"
    if re.search(
        r"(?i)(enfant|simple|premier contact|tr[eè]s jeunes|facile)", text[:600]
    ):
        return "Facile"
    return "Moyenne"


def clean_players(fields: dict, text: str) -> str:
    p = clean(fields.get("nombre de joueurs", ""))
    p = re.sub(r"^4 partir", "à partir", p)
    p = re.sub(r"de 2\s*4+\s*", "de 2 à 4 ", p)
    p = re.sub(r"de 2 à4", "de 2 à 4", p)
    if re.match(r"^à\s+par équipe", p):
        p = "4 " + p[1:].lstrip()
    p = p[:90].rstrip(" ,;")
    if p and len(p) > 2:
        return p
    m = re.search(r"(\d+)\s*(?:à|a|-)\s*(\d+)\s*joueurs?", text[:500], re.I)
    if m:
        return f"{m.group(1)} à {m.group(2)}"
    m = re.search(r"(\d+)\s*joueurs?", text[:500], re.I)
    if m:
        return m.group(1)
    return "2 ou plus"


def clean_cards(fields: dict, text: str) -> str:
    c = clean(fields.get("cartes") or fields.get("matériel") or "")
    c = re.split(r"(?i)Valeur des|But du|Nombre de", c)[0].strip(" ,;")
    if 5 < len(c) < 140 and not re.search(r"vaut un point|ordre décroissant", c, re.I):
        return c
    if re.search(r"2 jeux de 52|deux jeux de 52", text[:800], re.I):
        return "104 (2 jeux de 52)"
    if re.search(r"48 cartes", text[:800], re.I):
        return "48"
    if re.search(r"32 cartes|jeu de 32", text[:800], re.I):
        return "32"
    if re.search(r"52 cartes|jeu de 52", text[:800], re.I):
        return "52"
    return "52"


def clean_goal(fields: dict) -> str:
    g = clean(fields.get("but du jeu", ""))
    g = DECOR.sub("", g)
    g = re.sub(r"[A-Z0-9@]{8,}", "", g)
    g = re.sub(r"\s+", " ", g).strip(" .;")
    for stop in ["Chance de", "Déroulement", "Valeur des", "Nombre de"]:
        if stop.lower() in g.lower():
            g = re.split(stop, g, flags=re.I)[0].strip()
    if len(g) > 280:
        g = g[:277].rsplit(" ", 1)[0] + "…"
    return g if len(g) > 15 else "Voir la règle détaillée."


def clean_values(fields: dict):
    v = clean(fields.get("valeur des cartes") or fields.get("ordre des cartes") or "")
    if not v or len(v) < 10:
        return None
    v = re.sub(
        r"Dame,? Valet,? 10[^\.]*",
        "Dame, Valet, 10, 9, 8, 7, 6, 5, 4, 3, 2",
        v,
        count=1,
    )
    if len(v) > 550:
        v = v[:547].rsplit(" ", 1)[0] + "…"
    return v


def paragraphs(s: str, limit: int = 30) -> str:
    s = clean(s)
    if not s:
        return ""
    sents = re.split(r"(?<=[.!?])\s+(?=[A-ZÀÂÄÉÈÊËÎÏÔÖÙÛÜ«\-—0-9])", s)
    chunks, buf = [], []
    for sent in sents:
        sent = sent.strip()
        if not sent or re.fullmatch(r"\d+", sent):
            continue
        buf.append(sent)
        if len(buf) >= 3 or sum(len(x) for x in buf) > 450:
            chunks.append(" ".join(buf))
            buf = []
    if buf:
        chunks.append(" ".join(buf))
    return "\n\n".join(chunks[:limit])


def make_short(players, cards, goal, values, procedure) -> str:
    lines = ["### Matériel", "", f"{cards}. Joueurs : {players}."]
    if values:
        vv = values if len(values) <= 450 else values[:447] + "…"
        lines += ["", "### Valeur / ordre des cartes", "", vv]
    lines += ["", "### But", "", goal, "", "### Déroulement", ""]
    proc = re.sub(r"\s+", " ", procedure or "").strip()
    if len(proc) > 1600:
        cut = proc[:1600]
        sp = cut.rfind(". ")
        if sp > 700:
            cut = cut[: sp + 1]
        proc = cut
    lines.append(proc or "Voir la version longue.")
    return "\n".join(lines)


def extract_aliases(title: str, text: str) -> str:
    aliases = []
    for m in re.finditer(r'appel[eé]e?\s+aussi\s+[«"]([^»"]+)[»"]', text, re.I):
        aliases.append(m.group(1).strip())
    for m in re.finditer(r"Appel[eé]\s+(?:également|aussi)\s+([^,\n.(]+)", text, re.I):
        aliases.append(m.group(1).strip())
    for m in re.finditer(r"\(ou ([^)]+)\)", title):
        aliases.append(m.group(1).strip())
    m = re.search(r"\(ou ([^)]+)\)", text[:200], re.I)
    if m and len(m.group(1)) < 40:
        aliases.append(m.group(1).strip())
    return ", ".join(OrderedDict.fromkeys(aliases)) if aliases else "—"


def get_page_image_bytes(doc: fitz.Document, pdf_page_1based: int, cache: dict):
    if pdf_page_1based in cache:
        return cache[pdf_page_1based]
    if pdf_page_1based < 1 or pdf_page_1based > doc.page_count:
        cache[pdf_page_1based] = None
        return None
    page = doc[pdf_page_1based - 1]
    # Prefer medium embedded scan (~900px). Fall back to JPEG render ~110 dpi.
    best = None
    for img in page.get_images(full=True):
        xref = img[0]
        try:
            info = doc.extract_image(xref)
        except Exception:
            continue
        w, h = info["width"], info["height"]
        if w < 200 or h < 200:
            continue
        area = w * h
        score = area + (10**12 if 600 <= w <= 1600 else 0)
        cand = (score, info["image"], info["ext"], w, h)
        if best is None or score > best[0]:
            best = cand
    if best is None or best[3] > 2000 or len(best[1]) > 900_000:
        mat = fitz.Matrix(110 / 72, 110 / 72)
        pix = page.get_pixmap(matrix=mat, alpha=False)
        best = (0, pix.tobytes("jpeg"), "jpg", pix.width, pix.height)
    cache[pdf_page_1based] = (best[1], best[2], best[3], best[4])
    return cache[pdf_page_1based]


def convert(title, raw, book_page, pdf_page, slug, doc, img_cache) -> str:
    text = clean(raw)
    fields = {k: clean(v) for k, v in extract_fields(raw).items()}
    intro = get_intro(raw)
    procedure, variants, conseils = get_procedure(raw)
    procedure_p = paragraphs(procedure)
    variants_p = paragraphs(variants, 12) if variants else None
    conseils_p = paragraphs(conseils, 8) if conseils else None

    gtype = detect_type(title, text)
    players = clean_players(fields, text)
    cards = clean_cards(fields, text)
    goal = clean_goal(fields)
    values = clean_values(fields)
    diff = detect_difficulty(text)
    autres = extract_aliases(title, text)

    img_md = ""
    img_info = get_page_image_bytes(doc, pdf_page, img_cache) if pdf_page else None
    if img_info:
        data, ext, _w, _h = img_info
        if ext not in ("jpg", "jpeg", "png", "webp"):
            ext = "png"
        img_name = f"{slug}.{ext}"
        (IMG / img_name).write_bytes(data)
        img_md = (
            f"![Page du livre — {title}](images/{img_name})\n\n"
            f"*Illustration extraite de Garraud 1984, p. {book_page or '?'}.*\n"
        )

    short = make_short(players, cards, goal, values, procedure_p)

    long_parts = []
    if intro:
        long_parts += ["### Origine et présentation", "", intro, ""]
    long_parts += [
        "### Matériel et joueurs",
        "",
        f"- **Joueurs :** {players}",
        f"- **Cartes :** {cards}",
        "",
    ]
    if values:
        long_parts += ["### Valeur et ordre des cartes", "", values, ""]
    long_parts += [
        "### But du jeu",
        "",
        goal,
        "",
        "### Déroulement complet",
        "",
        procedure_p or "Se reporter au texte source.",
        "",
    ]
    ann = fields.get("annonces (ou combinaisons)") or fields.get("annonces")
    if ann:
        long_parts += ["### Annonces", "", ann[:900], ""]
    part = fields.get("particularités") or fields.get("particularites")
    if part:
        long_parts += ["### Particularités", "", part[:600], ""]

    md = [
        f"# {title}",
        "",
        f"**Nombre de joueurs :** {players}",
        f"**Nombre de cartes :** {cards}",
        f"**Difficulté :** {diff}",
        f"**Type :** {gtype}",
        f"**But :** {goal}",
        f"**Autres noms :** {autres}",
        "",
        "---",
        "",
    ]
    if img_md:
        md += ["## Illustration", "", img_md.rstrip(), "", "---", ""]
    md += [
        "## Règle courte",
        "",
        short,
        "",
        "---",
        "",
        "## Version longue",
        "",
        "\n".join(long_parts).strip(),
        "",
        "---",
        "",
        "## Source",
        "",
        "Christian Garraud, *Le grand livre des jeux de cartes et des réussites*, "
        "M.A. Éditions / France Loisirs, 1984"
        + (f", p. {book_page}." if book_page else "."),
    ]
    if conseils_p:
        md += ["", "## Conseils", "", conseils_p]
    if variants_p:
        md += ["", "## Variantes", "", variants_p]
    md.append("")
    return "\n".join(md)


def main(rebuild_all: bool = False):
    if not JEUX_PATH.exists():
        raise SystemExit(f"Missing {JEUX_PATH} — run classification first")
    if not PDF_PATH.exists():
        raise SystemExit(f"Missing PDF {PDF_PATH}")

    OUT.mkdir(exist_ok=True)
    IMG.mkdir(exist_ok=True)
    if rebuild_all:
        for p in OUT.glob("*.md"):
            p.unlink()
        for p in IMG.glob("*"):
            p.unlink()

    jeux = json.loads(JEUX_PATH.read_text(encoding="utf-8"))
    doc = fitz.open(str(PDF_PATH))
    img_cache = {}
    seen = {}
    written = []
    errors = []

    # Preload existing slugs if resuming
    existing = {p.stem for p in OUT.glob("*.md")}

    for i, sec in enumerate(jeux):
        old_slug = sec["slug"]
        src = SECTIONS / f"{old_slug}.txt"
        if not src.exists():
            errors.append((old_slug, "missing source"))
            continue
        title = nice_title(sec["title"])
        base = reslug(sec["title"])
        if base in seen:
            seen[base] += 1
            slug = f"{base}-{seen[base]}"
        else:
            seen[base] = 1
            slug = base

        if not rebuild_all and slug in existing and (IMG / f"{slug}.png").exists():
            # already done (png)
            written.append(
                {
                    "title": title,
                    "slug": slug,
                    "book_page": sec.get("book_page"),
                    "pdf_page": sec.get("pdf_page"),
                    "chars": (OUT / f"{slug}.md").stat().st_size,
                    "kind": "jeu",
                    "skipped": True,
                }
            )
            continue
        if not rebuild_all and slug in existing and any(IMG.glob(f"{slug}.*")):
            written.append(
                {
                    "title": title,
                    "slug": slug,
                    "book_page": sec.get("book_page"),
                    "pdf_page": sec.get("pdf_page"),
                    "chars": (OUT / f"{slug}.md").stat().st_size,
                    "kind": "jeu",
                    "skipped": True,
                }
            )
            continue

        raw = src.read_text(encoding="utf-8", errors="replace")
        try:
            md = convert(
                title,
                raw,
                sec.get("book_page"),
                sec.get("pdf_page"),
                slug,
                doc,
                img_cache,
            )
            (OUT / f"{slug}.md").write_text(md, encoding="utf-8")
            written.append(
                {
                    "title": title,
                    "slug": slug,
                    "book_page": sec.get("book_page"),
                    "pdf_page": sec.get("pdf_page"),
                    "chars": len(md),
                    "kind": "jeu",
                    "skipped": False,
                }
            )
        except Exception as e:
            errors.append((old_slug, str(e)))

        if (i + 1) % 25 == 0:
            print(f"... {i+1}/{len(jeux)}  md={len(list(OUT.glob('*.md')))}  img={len(list(IMG.glob('*')))}")

    doc.close()

    if REUSSITES_PATH.exists():
        reussites = json.loads(REUSSITES_PATH.read_text(encoding="utf-8"))
        (OUT / "_QUEUE_REUSSITES.json").write_text(
            json.dumps(
                {
                    "note": 'Réussites à traiter plus tard. Type obligatoire: "Réussite"',
                    "count": len(reussites),
                    "items": reussites,
                },
                ensure_ascii=False,
                indent=2,
            ),
            encoding="utf-8",
        )

    (OUT / "_MANIFEST_JEUX.json").write_text(
        json.dumps(written, ensure_ascii=False, indent=2), encoding="utf-8"
    )

    new = sum(1 for w in written if not w.get("skipped"))
    print(
        f"Done. total={len(written)} new={new} md={len(list(OUT.glob('*.md')))} "
        f"images={len(list(IMG.glob('*')))} errors={len(errors)}"
    )
    if errors[:5]:
        print("errors sample:", errors[:5])


if __name__ == "__main__":
    import sys

    rebuild = "--rebuild" in sys.argv
    main(rebuild_all=rebuild)
