#!/usr/bin/env python3
import json
import sqlite3
import unicodedata
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DB = ROOT / "site/v3/vault.sqlite"
REPORTS = [ROOT / f"site/v3/alias-audit-batch-{i}.json" for i in range(1, 7)]
AUDIT = ROOT / "assets/rules/game-names-audit.json"
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
    "yaniv": {"Main Verte", "Yanoff"},
}

PLACEHOLDERS = {"", "-", "—", "aucun", "aucune"}


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

    catalog = {
        "version": 1,
        "duplicates": DUPLICATES,
        "content_sources": dict(sorted(content_sources.items())),
        "games": dict(sorted(names.items())),
    }
    AUDIT.write_text(json.dumps(audit, ensure_ascii=False, indent=2) + "\n")
    CATALOG.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + "\n")
    print(f"{len(audit)} games audited; {len(names)} games after {len(DUPLICATES)} merges")


if __name__ == "__main__":
    main()
