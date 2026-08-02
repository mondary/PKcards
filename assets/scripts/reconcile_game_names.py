#!/usr/bin/env python3
import json
import re
import sqlite3
import unicodedata
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DB = ROOT / "site/v3/vault.sqlite"
REPORTS = [ROOT / f"site/v3/alias-audit-batch-{i}.json" for i in range(1, 7)]
AUDIT = ROOT / "assets/rules/game-names-audit.json"
FAMILY_REPORTS = [ROOT / f"site/v3/family-batch-{i}.json" for i in range(1, 7)]
FAMILY_AUDIT = ROOT / "assets/rules/game-families-audit.json"
CATALOG = ROOT / "site/v3/catalog.json"
WIKIMEDIA = ROOT / "assets/images/wikimedia.json"
LEGACY = ROOT / "site/v1/data.js"

# Only mechanically equivalent records belong here. Similar variants stay separate.
DUPLICATES = {
    "banque": "chien-rouge",
    "banque-russe": "crapette",
    "bataille-norvegienne": "shithead",
    "bog": "poque",
    "chasse-a-las": "coucou",
    "chasse-coeur": "coeurs",
    "cinq-cents": "500",
    "dame-de-pique": "coeurs",
    "gin-rami": "gin-rummy",
    "kilo": "paquet-de-merde",
    "la-triche": "menteur",
    "le-7-et-demi": "sept-et-demi",
    "le-go-fis": "pige-dans-le-lac",
    "le-kilo-de-merde": "paquet-de-merde",
    "le-slapjack": "frappe-le-valet",
    "mariage-chinois": "le-memory",
    "memoire-collective": "le-memory",
    "napoleon": "nap",
    "polignac": "jeu-de-valets",
    "rummy-500": "rami-500",
}

# Variants previously merged by mistake. Production can recreate their row
# from the former target while retaining their own rule already stored in KV.
RESTORED_VARIANTS = {
    "animals": "animaux",
    "beigne": "ascenseur",
    "bonjour-monsieur": "allo-jack",
    "petite-memoire": "golf",
    "pig": "bouchon",
}

# Local names explicitly confirmed by the product owner.
KEEP = {
    "flip": {"Lapsit"},
    "le-cabo-var": {"Le Cabo (var.)"},
    "yaniv": {"Main Verte", "Yanoff", "Yanouf"},
}

# Named variants need unambiguous search terms even when they share a family.
NAME_OVERRIDES = {
    "allo-jack": ["Allô Jack !", "Allô Jack ! (Québec)"],
    "animaux": ["Les Animaux", "Animal Sounds"],
    "ascenseur": ["L'Ascenseur", "Elevator", "Up and Down the River", "Oh Hell!", "Oh Pshaw!", "Oh Well!", "Oh Shit!", "Blob", "Blackout", "Contract Whist", "Nomination Whist", "Bust", "La Podrida"],
    "beigne": ["Le Beigne"],
    "bonjour-monsieur": ["Bonjour Monsieur, Bonjour Madame", "Bonjour Monsieur"],
    "bouchon": ["Le Bouchon", "Bouchon"],
    "golf": ["Golf", "Le Golf", "Four-Card Golf", "Polish Poker"],
    "petite-memoire": ["La Petite Mémoire", "La Petite Mémoire (Québec)"],
    "pig": ["Pig", "Spoons", "Hog", "Donkey"],
}

PLACEHOLDERS = {"", "-", "—", "aucun", "aucune"}

FAMILY_NAMES = {
    "Plis": "Levées",
    "Enchères et contrats": "Enchères",
    "Rami et combinaisons": "Rami",
    "Défausse et escalade": "Pioche & défausse",
    "Bataille et capture": "Bataille",
    "Pêche et capture de table": "Pêche",
    "Banque et casino": "Banque",
    "Poker et bluff": "Poker",
    "Patiences et solitaires": "Patience",
    "Mémoire et déduction": "Enquête",
    "Réaction et rapidité": "Bataille",
    "Familles et collections": "Familles",
    "Addition et calcul": "Addition",
    "Course et simulation": "Course",
    "Combat et duel": "Combat",
    "Rôle et déduction sociale": "Rôle",
    "Stops et séquences": "Appariemment",
    "Commerce et échange": "Commerce",
    "Adresse et construction": "Adresse",
    "Coopératifs": "Coopératif",
    "Compendiums": "Compendium",
    "Hasard pur": "Hasard",
}

MISTIGRI_FAMILIES = {
    "jeux-d-addition": "Addition",
    "jeux-d-appariemment": "Appariemment",
    "jeux-d-attaque": "Attaque",
    "jeux-d-enquete": "Enquête",
    "jeux-d-escalade": "Escalade",
    "jeux-de-banque": "Banque",
    "jeux-de-bataille": "Bataille",
    "jeux-de-combat": "Combat",
    "jeux-de-commerce": "Commerce",
    "jeux-de-course": "Course",
    "jeux-de-familles": "Familles",
    "jeux-de-levees": "Levées",
    "jeux-de-memoire": "Mémoire",
    "jeux-de-passes-de-cartes": "Passes de cartes",
    "jeux-de-peche": "Pêche",
    "jeux-de-pioche-defausse": "Pioche & défausse",
    "jeux-de-role": "Rôle",
    "jeux-divers": "Divers",
}

FAMILY_OVERRIDES = {
    "8-americain": ["Appariemment"],
    "allo-jack": ["Bataille", "Réaction"],
    "anandis": ["Enquête", "Appariemment"],
    "animals": ["Bataille"],
    "bataille-corse": ["Bataille"],
    "beigne": ["Levées"],
    "bouchon": ["Passes de cartes", "Réaction"],
    "bonjour-monsieur": ["Bataille", "Réaction"],
    "clubbed": ["Appariemment", "Attaque"],
    "crapaud": ["Pioche & défausse", "Commerce"],
    "eleusis": ["Enquête", "Appariemment"],
    "flip": ["Appariemment", "Réaction"],
    "frappe-le-valet": ["Bataille", "Réaction"],
    "gin-rummy": ["Pioche & défausse", "Rami"],
    "horse-race": ["Course", "Banque"],
    "identite": ["Mémoire", "Enquête"],
    "le-21": ["Addition", "Appariemment"],
    "le-kems": ["Commerce", "Réaction"],
    "paquet-de-merde": ["Passes de cartes", "Réaction"],
    "petite-memoire": ["Pioche & défausse", "Mémoire"],
    "pig": ["Passes de cartes"],
    "pouilleux": ["Passes de cartes"],
    "president": ["Escalade", "Pioche & défausse"],
    "rami-500": ["Pioche & défausse", "Rami"],
    "regicide": ["Coopératif", "Combat"],
    "yaniv": ["Pioche & défausse"],
}


def key(value: str) -> str:
    value = value.replace("’", "'").replace("‘", "'").strip().casefold()
    return "".join(c for c in unicodedata.normalize("NFKD", value) if not unicodedata.combining(c))


def unique(values: list[str]) -> list[str]:
    result = []
    seen = set()
    for value in values:
        value = " ".join(value.split()).strip()
        normalized = key(value)
        if normalized in PLACEHOLDERS or normalized in seen:
            continue
        seen.add(normalized)
        result.append(value)
    return result


def mistigri_family(slugs: list[str]):
    for slug in slugs:
        file = ROOT / f"assets/rules/rules_mistigri/{slug}.md"
        if not file.exists():
            continue
        for line in file.read_text().splitlines():
            if not line.startswith("> Source"):
                continue
            for segment, family in MISTIGRI_FAMILIES.items():
                if f"/{segment}/" in line:
                    return family
    return None


def mistigri_source(slug: str):
    file = ROOT / f"assets/rules/rules_mistigri/{slug}.md"
    if not file.exists():
        return None
    match = re.search(r"^> Source.*\((https?://[^)]+)\)", file.read_text(), re.MULTILINE)
    return match.group(1) if match else None


def restored_metadata(slug: str, title: str, source: str, legacy: dict) -> dict:
    if slug in legacy:
        game = legacy[slug]
        return {"source": source, **{field: game.get(field, "") for field in (
            "players", "cards", "difficulty", "type", "goal", "category", "color", "excerpt", "playerMin", "playerMax"
        )}, "title": title, "is_clm": 0, "is_mistigri": 0, "image": ""}

    markdown = (ROOT / f"assets/rules/rules_mistigri/{slug}.md").read_text()
    info = dict(re.findall(r"♦\s*\*\*([^*]+?)\*\*\s*:\s*(.*)", markdown))
    players = info.get("Nombre de joueurs", "")
    numbers = re.findall(r"\d+", players)
    plain = " ".join(re.sub(r"[#*_>`|!-]+", " ", markdown).split())
    return {
        "source": source, "title": title, "players": players, "cards": info.get("Matériel", ""),
        "difficulty": "", "type": "", "goal": info.get("Objectif", ""), "category": "mistigri",
        "color": "#a6e3a1", "excerpt": plain[:160], "playerMin": int(numbers[0]) if numbers else 0,
        "playerMax": int(numbers[1] if len(numbers) > 1 else numbers[0]) if numbers else 0,
        "is_clm": 0, "is_mistigri": 1, "image": "",
    }


def main() -> None:
    audit = (json.loads(AUDIT.read_text()) if AUDIT.exists()
             else [entry for report in REPORTS for entry in json.loads(report.read_text())])
    if len(audit) != 271 or len({entry["slug"] for entry in audit}) != 271:
        raise SystemExit("Expected 271 unique audited games")

    db = sqlite3.connect(DB)
    titles = dict(db.execute("SELECT slug,title FROM games"))
    editorial = dict(db.execute("SELECT slug,is_clm FROM games"))
    markdown_lengths = dict(db.execute("SELECT substr(path,8,length(path)-10),length(body) FROM kv WHERE path LIKE '/games/%.md'"))
    current = {}
    for slug, name in db.execute("SELECT slug,name FROM game_names ORDER BY rowid"):
        current.setdefault(slug, []).append(name)
    db.close()

    names = {}
    for entry in audit:
        slug = entry["slug"]
        title = titles.get(slug, entry["title"])
        removals = {key(name) for name in entry["remove"] if name not in KEEP.get(slug, set())}
        kept = [name for name in current.get(slug, entry["current_names"]) if key(name) not in removals or key(name) == key(title)]
        names[slug] = unique([title, *kept, *entry["add"], *sorted(KEEP.get(slug, set()))])
        if slug in NAME_OVERRIDES:
            names[slug] = NAME_OVERRIDES[slug]

    for duplicate, canonical in DUPLICATES.items():
        names[canonical] = unique([*names[canonical], *names[duplicate]])
        del names[duplicate]

    groups = {}
    for duplicate, canonical in DUPLICATES.items():
        groups.setdefault(canonical, [canonical]).append(duplicate)
    content_sources = {}
    for canonical, slugs in groups.items():
        source = canonical if editorial.get(canonical) else max(slugs, key=lambda slug: markdown_lengths.get(slug, 0))
        if source != canonical:
            content_sources[canonical] = source

    family_audit = (json.loads(FAMILY_AUDIT.read_text()) if FAMILY_AUDIT.exists()
                    else [entry for report in FAMILY_REPORTS for entry in json.loads(report.read_text())])
    if len(family_audit) != len(names) or {entry["slug"] for entry in family_audit} != set(names):
        raise SystemExit("Family audit must cover every canonical game exactly once")
    families = {}
    reverse_duplicates = {}
    for duplicate, canonical in DUPLICATES.items():
        reverse_duplicates.setdefault(canonical, []).append(duplicate)
    for entry in family_audit:
        slug = entry["slug"]
        mapped = [FAMILY_NAMES.get(family, family) for family in entry["families"]]
        if "Enquête" in mapped and any(word in slug for word in ("memory", "memoire", "compliquee", "selective", "thinkabi")):
            mapped[mapped.index("Enquête")] = "Mémoire"
        source_family = mistigri_family([slug, *reverse_duplicates.get(slug, [])])
        final = unique(FAMILY_OVERRIDES.get(slug, []) or ([source_family] if source_family else mapped))[:3]
        entry["families"] = final
        for family in final:
            families.setdefault(family, []).append(entry["slug"])

    sources = {}
    for entry in audit:
        original = entry["slug"]
        slug = DUPLICATES.get(original, original)
        sources[slug] = unique([*sources.get(slug, []), *entry["source_urls"], mistigri_source(original) or ""])
    sources = {slug: urls for slug, urls in sources.items() if urls}

    legacy_text = LEGACY.read_text()
    legacy_start = legacy_text.index("[")
    legacy_end = legacy_text.index("];", legacy_start)
    legacy = {game["id"]: game for game in json.loads(legacy_text[legacy_start:legacy_end + 1])}
    restored = {slug: restored_metadata(slug, names[slug][0], source, legacy)
                for slug, source in RESTORED_VARIANTS.items()}
    wikimedia = json.loads(WIKIMEDIA.read_text())["images"]
    if set(wikimedia) != set(names):
        raise SystemExit("Wikimedia manifest must cover every canonical game exactly once")
    image_credits = {
        slug: {
            "title": image["file"].removeprefix("File:"),
            "author": image["author"],
            "license": image["license"],
            "license_url": image.get("license_url", ""),
            "url": image["page_url"],
            "rationale": image["rationale_fr"],
        }
        for slug, image in wikimedia.items()
    }

    catalog = {
        "version": 1,
        "duplicates": DUPLICATES,
        "restored_variants": restored,
        "content_sources": dict(sorted(content_sources.items())),
        "families": dict(sorted(families.items())),
        "games": dict(sorted(names.items())),
        "images": {slug: f"images/games/{slug}.webp" for slug in sorted(names)},
        "image_credits": image_credits,
        "sources": dict(sorted(sources.items())),
    }
    AUDIT.write_text(json.dumps(audit, ensure_ascii=False, indent=2) + "\n")
    FAMILY_AUDIT.write_text(json.dumps(family_audit, ensure_ascii=False, indent=2) + "\n")
    CATALOG.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + "\n")
    print(f"{len(audit)} games audited; {len(names)} games after {len(DUPLICATES)} merges")


if __name__ == "__main__":
    main()
