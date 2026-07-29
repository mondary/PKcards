#!/usr/bin/env python3
"""Scan all rules directories and build data.sqlite for the rules-editor."""
import sqlite3, re, sys
from pathlib import Path

RULES_DIR = Path(__file__).resolve().parent.parent.parent / 'rules'
DB_PATH = Path(__file__).resolve().parent / 'data.sqlite'

SOURCES = {
    'clm':       'rules_clm',
    'bycicle':   'rules_bycicle',
    'edimag100': 'rules_edimag100',
    'fetjain32': 'rules_fetjain32',
    'rules2':    'rules2/jeux',
    'rules3':    'rules3',
    'original':  '.',
}

def extract_title(md):
    m = re.search(r'^#\s+(.+)$', md, re.MULTILINE)
    return m.group(1).strip() if m else ''

def extract_sections(md):
    """Split markdown by H2 headings. Returns list of {heading, content}."""
    sections = []
    current_h = '(En-tête)'
    current_c = []
    for line in md.split('\n'):
        if re.match(r'^##\s+', line):
            if current_c:
                sections.append({'heading': current_h, 'content': '\n'.join(current_c).strip()})
            current_h = re.sub(r'^##\s+', '', line).strip()
            current_c = [line]
        else:
            current_c.append(line)
    if current_c:
        sections.append({'heading': current_h, 'content': '\n'.join(current_c).strip()})
    return sections

def main():
    db = sqlite3.connect(DB_PATH)
    db.row_factory = sqlite3.Row
    db.execute('''CREATE TABLE IF NOT EXISTS versions (
        id INTEGER PRIMARY KEY,
        slug TEXT, source TEXT, filepath TEXT,
        title TEXT, content TEXT, wordcount INTEGER,
        sections_json TEXT,
        UNIQUE(slug, source, filepath)
    )''')
    db.execute('DELETE FROM versions')

    total = 0
    for source_name, subdir in SOURCES.items():
        full_dir = RULES_DIR / subdir
        if not full_dir.is_dir():
            continue
        for md_file in sorted(full_dir.glob('*.md')):
            content = md_file.read_text(encoding='utf-8', errors='replace')
            slug = md_file.stem.lower()
            title = extract_title(content) or slug
            wc = len(content.split())
            rel = str(md_file.relative_to(RULES_DIR))
            sections = extract_sections(content)
            import json
            sj = json.dumps(sections, ensure_ascii=False)
            db.execute(
                'INSERT OR REPLACE INTO versions (slug,source,filepath,title,content,wordcount,sections_json) VALUES (?,?,?,?,?,?,?)',
                (slug, source_name, rel, title, content, wc, sj)
            )
            total += 1

    db.commit()

    # Stats
    games = db.execute('SELECT slug, COUNT(*) as n, GROUP_CONCAT(DISTINCT source) as sources FROM versions GROUP BY slug HAVING n > 1 ORDER BY n DESC').fetchall()
    total_games = db.execute('SELECT COUNT(DISTINCT slug) FROM versions').fetchone()[0]

    print(f'{total} versions importées, {total_games} jeux uniques')
    print(f'{len(games)} jeux avec plusieurs versions:')
    for g in games[:15]:
        print(f'  {g["slug"]:30s} ({g["n"]} versions: {g["sources"]})')
    if len(games) > 15:
        print(f'  ... et {len(games)-15} autres')

    db.close()

if __name__ == '__main__':
    main()
