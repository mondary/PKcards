#!/usr/bin/env python3
import json
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

# Only mechanically equivalent records belong here. Similar variants stay separate.
DUPLICATES = {
    "animals": "animaux",
    "banque": "chien-rouge",
    "banque-russe": "crapette",
    "bataille-norvegienne": "shithead",
    "beigne": "ascenseur",
    "bog": "poque",
    "bonjour-monsieur": "allo-jack",
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
    "petite-memoire": "golf",
    "pig": "bouchon",
    "polignac": "jeu-de-valets",
    "rummy-500": "rami-500",
}

# Local names explicitly confirmed by the product owner.
KEEP = {
    "flip": {"Lapsit"},
    "yaniv": {"Main Verte", "Yanoff", "Yanouf"},
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
    "bataille-corse": ["Bataille"],
    "bouchon": ["Passes de cartes", "Réaction"],
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
        slug = DUPLICATES.get(entry["slug"], entry["slug"])
        sources[slug] = unique([*sources.get(slug, []), *entry["source_urls"]])
    sources = {slug: urls for slug, urls in sources.items() if urls}

    catalog = {
        "version": 1,
        "duplicates": DUPLICATES,
        "content_sources": dict(sorted(content_sources.items())),
        "families": dict(sorted(families.items())),
        "games": dict(sorted(names.items())),
        "sources": dict(sorted(sources.items())),
    }
    AUDIT.write_text(json.dumps(audit, ensure_ascii=False, indent=2) + "\n")
    FAMILY_AUDIT.write_text(json.dumps(family_audit, ensure_ascii=False, indent=2) + "\n")
    CATALOG.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + "\n")
    print(f"{len(audit)} games audited; {len(names)} games after {len(DUPLICATES)} merges")


if __name__ == "__main__":
    main()
