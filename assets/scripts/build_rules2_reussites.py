#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Build rules_garraud/reussite/*.md for the 223 pending "réussites" (patiences/solitaires)
from Garraud 1984. Reads the queue in rules_garraud/reussite/_QUEUE_REUSSITES.json and the
raw OCR text in /tmp/garraud_sections/{slug}.txt, applies cleanup, and
writes one markdown file per réussite following the same template as the
jeux builder. Images are linked only if a png is already present in
rules_garraud/images/.
"""
from __future__ import annotations

import json
import re
from collections import OrderedDict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "assets" / "rules" / "rules_garraud" / "reussite"
IMG = OUT / "images"
SECTIONS = Path("/tmp/garraud_sections")
QUEUE_PATH = OUT / "_QUEUE_REUSSITES.json"

# ---------------------------------------------------------------------------
# OCR cleanup shared with the jeux builder
# ---------------------------------------------------------------------------
OCR = [
    (r"céte", "côte"),     (r"frangaise", "française"), (r"frangais", "français"),
    (r"siécle", "siècle"), (r"\bsiecle\b", "siècle"),
    (r"\bpremiere\b", "première"), (r"\bdeuxieme\b", "deuxième"),
    (r"\btroisieme\b", "troisième"), (r"\bquatrieme\b", "quatrième"),
    (r"\bcinquieme\b", "cinquième"), (r"\bsixieme\b", "sixième"),
    (r"\bderniere\b", "dernière"),
    (r"\bBati?r\b", "Bâtir"), (r"\bbati?r\b", "bâtir"),
    (r"\bmani[eé]re\b", "manière"), (r"\bpremi[eé]re\b", "première"),
    (r"\bderni[eé]re\b", "dernière"),
    (r"premiéres?", "première"), (r"deuxiémes?", "deuxième"),
    (r"troisiémes?", "troisième"), (r"quatriémes?", "quatrième"),
    (r"cinquiémes?", "cinquième"), (r"septiémes?", "septième"),
    (r"sixiémes?", "sixième"), (r"huitiémes?", "huitième"),
    (r"neuviémes?", "neuvième"), (r"dixiémes?", "dixième"),
    (r"onziémes?", "onzième"), (r"douziémes?", "douzième"),
    (r"treiziémes?", "treizième"), (r"quatorziémes?", "quatorzième"),
    (r"quinziémes?", "quinzième"), (r"seiziémes?", "seizième"),
    (r"dix-septiémes?", "dix-septième"), (r"dix-huitiémes?", "dix-huitième"),
    (r"dix-neuviémes?", "dix-neuvième"), (r"vintiémes?", "vingtième"),
    (r"régles", "règles"), (r"régle", "règle"), (r"méme", "même"),
    (r"aprés", "après"), (r"étre", "être"), (r"trés", "très"),
    (r"intérét", "intérêt"), (r"enchéres", "enchères"), (r"enchére", "enchère"),
    (r"particuliére", "particulière"), (r"got de", "goût de"),
    (r"caractére", "caractère"), (r"\boti\b", "où"), (r"\boi\b", "où"),
    (r"grace", "grâce"), (r"Coeur", "Cœur"), (r"coeur", "cœur"),
    (r"Cceur", "Cœur"), (r"I’on", "l'on"), (r"I'on", "l'on"),
    (r"I’", "l'"), (r"I'", "l'"), (r"\|’", "l'"), (r"\|'", "l'"),
    (r" 4 ", " à "), (r"^4 ", "à "), (r"4a ", "à "), (r"4 partir", "à partir"),
    (r"cing ", "cinq "), (r"Cing ", "Cinq "), (r"commengant", "commençant"),
    (r"facons", "façons"), (r"enléve", "enlève"), (r"considére", "considère"),
    (r"entrainer", "entraîner"), (r"cotiteuse", "coûteuse"),
    (r"achéte", "achète"), (r"bitches", "bûches"), (r"Bacarra", "Baccara"),
    (r"Punité", "l'unité"), (r"ltalie", "Italie"), (r"Etats-Unis", "États-Unis"),
    (r"fagon", "façon"), (r"jusqu’a", "jusqu'à"), (r"jusqu'a", "jusqu'à"),
    (r"Quiils", "Qu'ils"), (r"premières de", "première de"),
    (r"Pun des", "l'un des"), (r"II ", "Il "), (r"I] ", "Il "),
    (r"de fagon", "de façon"), (r"commengé", "commencé"),
    (r"reussite", "réussite"), (r"Reussite", "Réussite"),
    (r"interesse", "intéresse"), (r"interêt", "intérêt"),
    (r"batir", "bâtir"), (r"revele", "révèle"), (r"clé", "clé"),
    (r"nétes", "nêtes"), (r"étes", "êtes"), (r"étres", "êtres"),
    (r"méres", "mères"), (r"péres", "pères"), (r"fréres", "frères"),
    (r"éra", "ère"), (r"prés", "près"),
    (r"sépares?", "séparés"), (r"communiqués?", "communique"),
    (r"indifférente?", "indifférentes"),
    (r"premiére", "première"), (r"derniére", "dernière"),
    (r"troisiéme", "troisième"), (r"quatriéme", "quatrième"),
    (r"complétement", "complètement"), (r"compléte", "complète"),
    (r"situe?s?", "situé"), (r"bati", "bâti"), (r"equi", "équi"),
    (r"indiffferente?", "indifférentes"),
    (r"celle-?ci", "celle-ci"), (r"celui-?ci", "celui-ci"),
    (r"aujourdhui", "aujourd'hui"), (r"cf", "cf."),
    (r"\ba pu\b", "a pu"),  # don't touch
    (r"égalernent", "également"), (r"vraiernent", "vraiment"),
    (r"commencant", "commençant"),
    (r"sérni?es?", "séries"),
    (r"\bTr[eé]fle", "Trèfle"),
    (r"\bt[eé]te\b", "tête"),
    (r"\bcr[eé]er", "créer"),
    (r"\bapparait", "apparaît"),
    (r"\bparait", "paraît"),
    (r"\bconna[ïi]tre", "connaître"),
    (r"\bdispara[ïi]tre", "disparaître"),
    (r"\bclairement", "clairement"),
    (r"\bnetoyer", "nettoyer"),
    (r"\bempocher", "empocher"),
    (r"\baussit[6oô]t", "aussitôt"),
    (r"\bPon\b", "l'on"),
    (r"\bPo[nt] ?(peut|veut|d[eé]sire|sait|joue|est|a )", r"l'on \1"),
    (r"\bni a l", "ni à l"),
    (r"\bni a (?:un|une)\b", "ni à un"),
    (r"\bqu[ei] ?l?on ", "qu'on "),
    (r"\bs? a l'une\b", " à l'une"),
    (r"\bs? a l'autre\b", " à l'autre"),
    (r"\bcr[eé]e\b", "crée"),
    (r"\bInteresse\b", "Intéresse"),
    (r"\bconditian\b", "condition"),
]
# Lines that look like floating image captions or ascii-art figure remnants.
LEGEND_NOISE = re.compile(
    r"(?i)\b(?:triangle|réserve|talon|rebut|exemple|cave|cercle|base|séries?|"
    r"clavier|croix|figure|cour|partie|joueur)\b"
)
ASCII_OR_DECO_LINE = re.compile(
    r"^(?:[\+\-\*\.\|\<\>\/\\\:oostxvOAHPEDSGBF\=\~\(\)\[\]\{\}\d\s]{12,}|"
    r"[\+\-\*\.][\+\-\*\.\s]{10,}|"
    r"[A-ZÀ-Ÿ][a-zà-ÿ]+(?:\s+[a-zà-ÿ]+){0,3}\s*\:?[\s\d\+\-\*\.\<\>\:]{8,})$"
)
DECOR = re.compile(
    r"(?:POV|DOV|BOV|B0V|POP|DBO|DSP|ESO|SSO|SHO|GSO|VSS|VED|EOY|HOV|ADO|APY|"
    r"ESDOV|YOVA|HDOV|AOV|DAOV|BDOV|BEOV|BDO|ADOY|EROY|SBYV|PSYV|"
    r"BOYV|RYOV|EBOV|EBYV|EDOV|EHOV|EPOV|EROV|EYOYV|EYOV|EROYV|"
    r"SBOV|SBYV|SEOY|SEOYV|SPYOYV|SBYOYV|EBOYV|EBYOYV|SAYOV)[A-Z0-9@®©]*",
    re.I,
)
R_REUSSITE_MARK = re.compile(r"^\s*R\s*\|", re.M)

# Anything that looks like a layout figure (POV DOV ...) or ASCII art row
ASCII_ART_LINE = re.compile(
    r"^(?:[POV@ESDOBHGFE0-9\s\|\~\(\)\,\.\+\*\-\=\<\>\/\\]{12,}|"
    r"[\+\-\*\.\|]\s?){12,}$"
)

# Long tables of values like "Points: 11, 10, 4, 3, 2, 0" already cleaned
POINTS_FIX = re.compile(r"(Points|Pts)\s*\)\s*([^\.]{0,120})", re.I)


def clean(s: str) -> str:
    if not s:
        return ""
    # Strip the R | marker prefix (réussite bullet)
    s = R_REUSSITE_MARK.sub("", s)
    s = DECOR.sub("", s)
    s = re.sub(r"[P@V]{3,}", "", s)
    # Hyphenation across line breaks BEFORE joining
    s = re.sub(r"(\w)-\n(\w)", r"\1\2", s)
    # Drop page-number-only lines
    s = re.sub(r"(?m)^\s*\d{1,3}\s*$", "", s)
    # Drop ascii-art / decorative rows
    s = re.sub(
        r"(?m)^[POV@ESDOBHGFE0-9\s\|\~\(\)\,\.\+\*\-\<\>\/\\\:]{12,}$", "", s)
    # OCR word fixes
    for a, b in OCR:
        if isinstance(b, str):
            s = re.sub(a, b, s)
    # Repair " 4 " → " à " in known contexts
    s = re.sub(r"\b4 de\b", "à de", s)
    s = re.sub(r"\b4 (la|les|son|sa|ses|leur|l'|deux|trois|quatre|cinq|six|"
               r"sept|huit|neuf|dix|chaque|tour|droite|gauche|partir|son|"
               r"leur|l’|des)\b", r"à \1", s)
    s = re.sub(r"\b4 part", "à part", s)
    # "Dame Valet 10..." missing separators
    s = re.sub(r"Dame Valet 10[0-9,\s]+",
               "Dame, Valet, 10, 9, 8, 7, 6, 5, 4, 3, 2", s)
    # Strip the floating "+ » Triangle ... Cours de partie" image captions
    cap_re = re.compile(
        r"[\+\*\/\\\<\>\u00ab\u00bb\"']{1,}\s*"
        r"(?:Triangle|R[eé]serve|Talon|Rebut|Cave|Cercle|Base|S[eé]ries?|"
        r"Croix|Figure|Clavier|Cour|Joueur|Cours de partie|Exemple|Tour|Pied|"
        r"Sommet|T[eé]te|Aile|Carr[eé]|Pyramide)[^\n.]{0,180}"
        r"(?=Le |La |Les |On |Lorsqu|D[eé]s|Chaque|Au |En |Si |Pour |Tout|"
        r"Apr[eè]s|$)",
    )
    s = cap_re.sub(" ", s)
    # Garbled fragments like "ahouldoan an of: 3 AE"
    s = re.sub(r"\s+[A-ZÀ-Ÿ]{1,3}\s+(?:of|of:|an|an:)\s+\S{1,4}\s+"
               r"(?:[A-ZÀ-Ÿ]{1,3}|of|an)", " ", s)
    # "equilatèrel:" typo
    s = re.sub(r"[eé]quilat[eè]r[eel]+l?\s*:", "équilatéral :", s)
    # Trailing single-letter layout fragments after periods (". V", ". F")
    s = re.sub(r"[\.\,\;]\s+[A-Z]\s+(?=[A-ZÀ-Ÿ][a-zà-ÿ])", ". ", s)
    s = re.sub(r"\.\s+[VEF]\s*\Z", ".", s)

    # ---------- CAPTION LINE FILTER (before line-join) ----------
    # Process each line individually to drop ascii-art figure captions
    # mixed into the text (e.g. "Base -¢ + @n~ < < ¢ © > > nes").
    def _is_caption_line(line: str) -> bool:
        stripped = line.strip()
        if not stripped or len(stripped) < 6:
            return False
        symbols = sum(1 for c in stripped if c in
                      "-¢@+*~<>©:;,()[]{}=\\/!?§&_")
        letters = sum(1 for c in stripped if c.isalpha())
        digits = sum(1 for c in stripped if c.isdigit())
        if symbols >= 3 and letters <= 20 and len(stripped) <= 80:
            return True
        if symbols >= 5 and letters <= 30:
            return True
        if digits >= 2 and letters <= 4 and symbols >= 1:
            return True
        if re.search(r"\(\d+\s*cartes?\)", stripped) and symbols >= 1:
            return True
        if len(stripped) <= 5 and not re.search(r"[a-zA-ZÀ-ÿ]{3,}", stripped):
            return True
        return False

    kept_lines = [ln for ln in s.split("\n") if not _is_caption_line(ln)]
    s = "\n".join(kept_lines)
    # -------------------------------------------------------------

    # Join soft-wrapped lines
    s = re.sub(r"(?<!\n)\n(?!\n)", " ", s)
    s = re.sub(r"[ \t]+", " ", s)
    # Repair hyphen-glued words ("immé- diate")
    s = re.sub(r"(\w)-\s+(\w)", r"\1\2", s)
    # Collapse spaces
    s = re.sub(r"\s{2,}", " ", s)
    s = re.sub(r"\s+([,.;:])", r"\1", s)
    # "a partir", "a l'aide", "a sept" → "à …"
    s = re.sub(r"(?<![\wÀ-ÿ])a (partir|l'aide|l’aide|droite|gauche|sept|"
               r"huit|neuf|dix|onze|douze|treize|quatorze|quinze|seize|"
               r"deux|trois|quatre|cinq|six|son|sa|leur|tour|chaque|"
               r"troisième|deuxième|première|condition|condition de|"
               r"condition que|nouveau|partir de)\b", r"à \1", s)
    # Restore "4 de X" for card values wrongly rewritten (heuristic: keep)
    # nothing — most "à de Carreau" cases are actually OCR "4 de Carreau"
    s = re.sub(r"\bà de (Carreau|Cœur|Pique|Trèfle|Coeur|Trefle)\b",
               r"4 de \1", s)
    # Fix "arréte" -> "arrête" and friends
    s = re.sub(r"arréte", "arrête", s)
    # "On ne peut-remplacer" -> "On ne peut remplacer"
    s = re.sub(r"\bpeut-([a-z])", r"peut \1", s)
    # Trailing " F " or " V " artefacts between sentences
    s = re.sub(r"\s+[FVE]\s+(?=[A-ZÀ-Ÿ][a-zà-ÿ])", " ", s)
    # "a [infinitive]" almost always means "à [infinitif]" in card rules
    s = re.sub(r"(?<![\wÀ-ÿ])a (alimenter|ouvrir|continuer|former|constituer|"
               r"créer|prendre|remplir|jouer|vider|empiler|compléter|"
               r"permettre|empêcher|retourner|déplacer|utiliser|aller|"
               r"combler|terminer|construire|bâtir|commencer|décider|"
               r"ajouter|installer|disposer|exécuter|tirer|donner|garder|"
               r"condition que)\b", r"à \1", s)
    # " le à de X " → " le 4 de X " when followed by a suit
    s = re.sub(r"\ble à de (Carreau|Cœur|Pique|Trèfle|Coeur|Trefle)\b",
               r"le 4 de \1", s)
    # "(un|le) à sur (un|le) X" → "4 sur X" (the value 4 misread as "à")
    s = re.sub(r"\b(un|le) à sur (un|le) (\d|[Vv]alet|[Dd]ame|[Rr]oi|[Aa]s)\b",
               r"\1 4 sur \2 \3", s)
    s = re.sub(r"\bl'?à de\b", "l'as de", s)  # rare
    # Strip lone "+", "*", "/" punctuation leftover from image captions
    s = re.sub(r"(?<=[\.\!\?])\s+\+\s+", " ", s)
    s = re.sub(r"(?<=[\.\!\?])\s+[\*\>\<\/]\s+", " ", s)
    s = re.sub(r"^\s*[\+\*\>\<\/]\s+", "", s)
    # Fix double "à à"
    s = re.sub(r"\bà à\b", "à", s)
    return s.strip()


def nice_title(raw: str) -> str:
    t = re.sub(r"\s+", " ", raw.strip())
    # Strip trailing codes used as section markers in OCR
    t = re.sub(r"\s+(?:Wh|Mh|Wb|Ny|\(.*?\))$", "", t)
    t = re.sub(r"\s+\(.*?\)\s*$", "", t)
    t = re.sub(r"\s+[A-Z0-9\}\{\)\(]{2,}$", "", t)
    letters = [c for c in t if c.isalpha()]
    if letters and sum(1 for c in letters if c.isupper()) / len(letters) >= 0.7:
        small = {"DE","DU","DES","ET","A","À","AU","AUX","LA","LE","LES","OU","EN",
                 "SUR","D","L","AVEC","SANS","PAR","POUR","DANS"}
        words = []
        for i, p in enumerate(t.replace("'", "\u2019").split(" ")):
            if "\u2019" in p:
                a, b = p.split("\u2019", 1)
                words.append((a[:1].upper()+a[1:].lower()) + "\u2019" +
                             (b[:1].upper()+b[1:].lower() if b else ""))
            elif i > 0 and p.upper() in small:
                words.append(p.lower())
            else:
                words.append(p[:1].upper()+p[1:].lower() if p else p)
        t = " ".join(words)
    # Fix leading L -> L'
    if t.startswith("L") and len(t) > 1 and t[1].isupper():
        t = "L'" + t[1:]
    return t


def reslug(title: str) -> str:
    s = title.lower().replace("\u2019", "'").replace("'", "")
    for a, b in [("é","e"),("è","e"),("ê","e"),("ë","e"),("à","a"),("â","a"),
                 ("ä","a"),("ô","o"),("ö","o"),("ù","u"),("û","u"),("ü","u"),
                 ("î","i"),("ï","i"),("ç","c"),("œ","oe")]:
        s = s.replace(a, b)
    s = re.sub(r"^(le|la|les|un|une)[\s-]+", "", s)
    s = re.sub(r"^l(?=[aeiouy])", "", s)
    s = re.sub(r"[^a-z0-9]+", "-", s).strip("-")
    return re.sub(r"-+", "-", s) or "reussite"


# ---------------------------------------------------------------------------
# Field extraction for réussites
# ---------------------------------------------------------------------------
FIELD_RE = re.compile(
    r"(Ordre des cartes|Ordres des cartes|Valeur des cartes|Tableau|But du jeu|"
    r"But de jeu|But de la réussite|Chance de réussite|Cartes|"
    r"Temps moyen|Annonces)\s*:\s*",
    re.I,
)


def extract_fields(text: str) -> "OrderedDict[str, str]":
    fields: "OrderedDict[str, str]" = OrderedDict()
    matches = list(FIELD_RE.finditer(text))
    for i, m in enumerate(matches):
        key = m.group(1).lower().rstrip()
        start = m.end()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
        rest = text[start:end]
        dm = re.search(r"D[eé]roulement de la r[eé]ussite", rest, re.I)
        if dm:
            rest = rest[: dm.start()]
        val = clean(rest)
        if len(val) > 5:
            fields[key] = val
    return fields


def get_procedure(raw: str):
    m = re.search(r"D[eé]roulement de la r[eé]ussite\s*:?\s*(.*)", raw, re.I | re.S)
    if not m:
        m = re.search(r"D[eé]roulement du la r[eé]ussite\s*:?\s*(.*)", raw, re.I | re.S)
    if not m:
        m = re.search(r"D[eé]roulement de la partie\s*:?\s*(.*)", raw, re.I | re.S)
    if not m:
        # Fall back to whole text minus the field headers area
        return clean(raw), None, None, None, clean(raw)
    body = m.group(1)
    intro = raw[: m.start()]
    variants = exemples = conseils = None
    em = re.search(r"(?i)\bExemple\b\s*:?\s*(.*)", body, re.S)
    if em:
        exemples = clean(em.group(1))
        body = body[: em.start()]
    vm = re.search(r"(?i)\bVARIANTE[S]?\b\s*:?\s*(.*)", body, re.S)
    if vm:
        variants = clean(vm.group(1))
        body = body[: vm.start()]
    cm = re.search(r"(?i)\bConseils?\b\s*:?\s*(.*)", body, re.S)
    if cm:
        conseils = clean(cm.group(1))
        body = body[: cm.start()]
    return clean(body), variants, conseils, exemples, clean(intro)


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


# ---------------------------------------------------------------------------
# Difficulty / type
# ---------------------------------------------------------------------------
def chance_to_difficulty(chance: str) -> str:
    """Convert '1 sur N' style odds to a difficulty bucket."""
    if not chance:
        return "Moyenne"
    nums = re.findall(r"\d+", chance)
    if not nums:
        # "doit être réussie dans la majorité des cas" etc.
        if re.search(r"majorit|r[eé]ussie|facilement|presqu", chance, re.I):
            return "Facile"
        return "Moyenne"
    try:
        n = max(int(x) for x in nums)
    except ValueError:
        return "Moyenne"
    if "100" in chance and n >= 50:
        return "Très difficile"
    if n >= 20:
        return "Difficile"
    if n >= 8:
        return "Moyenne"
    return "Facile"


def detect_type(text: str) -> str:
    blob = text[:1200].lower()
    types = []
    if re.search(r"d[eé]croiss|s[eé]ries? provisoires?", blob):
        types.append("Séries décroissantes")
    if re.search(r"croiss|base.*as|sur les as", blob):
        types.append("Séries croissantes")
    if re.search(r"altern(?:é|ee).*(?:rouge|noir)", blob):
        types.append("Alternance rouge/noir")
    if re.search(r"couple|par paires?|paires? de m[eê]me", blob):
        types.append("Par paires")
    if re.search(r"pyramid|carr[eé]|cercle|étoile|croix|triangle", blob):
        types.append("Géométrique")
    if re.search(r"r[eé]serve|talon|rebut|cave", blob):
        types.append("Avec talon/rebut")
    if not types:
        types = ["Réussite classique"]
    out = []
    for x in types:
        if x not in out:
            out.append(x)
    return ", ".join(out)


def clean_cards(fields) -> str:
    c = clean(fields.get("cartes", ""))
    if not c:
        return "52"
    c = re.split(r"(?i)Valeur des|But du|Ordre des|Tableau", c)[0].strip(" ,;")
    if 3 < len(c) < 140 and not re.search(r"vaut un point|ordre d[eé]croissant", c, re.I):
        return c
    return "52"


def clean_chance(fields) -> str:
    c = clean(fields.get("chance de réussite", ""))
    # Keep only the "1 sur N" pattern (and an optional following sentence)
    m = re.search(r"\d+\s*sur\s*\d+(?:\s+(?:à|a)\s*\d+)?|[^\n.]{0,40}"
                  r"(?:majorit|presqu|r[eé]ussie|facilement)[^\n.]{0,40}", c, re.I)
    if m:
        c = m.group(0).strip()
    c = re.split(r"(?i)D[eé]roulement|Exemple|Valeur|Ordre|Tableau", c)[0]
    c = re.sub(r"[\.\,]\s*\Z", "", c.strip())
    if len(c) > 90:
        c = c[:87].rsplit(" ", 1)[0] + "…"
    return c


def clean_tableau(fields) -> str:
    t = clean(fields.get("tableau", ""))
    t = DECOR.sub("", t)
    # Trim noise after end of sentence
    if len(t) > 700:
        t = t[:697].rsplit(" ", 1)[0] + "…"
    return t.strip(" .;")


def clean_but(fields) -> str:
    b = clean(fields.get("but du jeu") or fields.get("but de jeu") or
              fields.get("but de la réussite", ""))
    b = DECOR.sub("", b)
    b = re.sub(r"[A-Z0-9@]{8,}", "", b)
    b = re.sub(r"\s+", " ", b).strip(" .;")
    for stop in ["Chance de", "Déroulement", "Valeur des", "Ordre des", "Tableau"]:
        if stop.lower() in b.lower():
            b = re.split(stop, b, flags=re.I)[0].strip()
    if len(b) > 280:
        b = b[:277].rsplit(" ", 1)[0] + "…"
    return b if len(b) > 12 else "Voir la règle détaillée."


def clean_ordre(fields) -> str:
    o = (fields.get("ordre des cartes") or fields.get("ordres des cartes") or
         fields.get("valeur des cartes", ""))
    o = clean(o)
    if not o or len(o) < 8:
        return ""
    o = re.sub(r"Dame,? Valet,? 10[^\.]*",
               "Dame, Valet, 10, 9, 8, 7, 6, 5, 4, 3, 2", o, count=1)
    if len(o) > 500:
        o = o[:497].rsplit(" ", 1)[0] + "…"
    return o


# ---------------------------------------------------------------------------
# Image lookup
# ---------------------------------------------------------------------------
def find_image(slug: str, title: str) -> str | None:
    """Return relative image path if a file already exists for this entry."""
    candidates = [slug, slug.replace("-", ""), slug.replace("le-", "").replace("la-", "")]
    for c in candidates:
        for ext in ("png", "jpg", "jpeg", "webp"):
            p = IMG / f"{c}.{ext}"
            if p.exists():
                return f"images/{p.name}"
    return None


# ---------------------------------------------------------------------------
# Markdown builder
# ---------------------------------------------------------------------------
def build_markdown(item: dict, raw: str) -> str:
    title = nice_title(item["title"])
    slug = item["slug"]
    book_page = item.get("book_page")

    fields = extract_fields(raw)
    procedure, variants, conseils, exemples, intro_raw = get_procedure(raw)
    procedure_p = paragraphs(procedure)
    variants_p = paragraphs(variants, 10) if variants else None
    conseils_p = paragraphs(conseils, 8) if conseils else None
    exemples_p = paragraphs(exemples, 6) if exemples else None

    # Short introduction paragraph (text before "Ordre des cartes:" etc.)
    intro = ""
    if intro_raw:
        m = re.search(
            r"(Ordre des cartes|Valeur des cartes|Tableau\s*:|But du jeu|"
            r"Chance de réussite|Cartes\s*:|D[eé]roulement)",
            intro_raw, re.I)
        if m:
            intro = clean(intro_raw[: m.start()])
            if len(intro) > 600:
                intro = intro[:597].rsplit(" ", 1)[0] + "…"
        if len(intro) < 40:
            intro = ""

    cards = clean_cards(fields)
    ordre = clean_ordre(fields)
    tableau = clean_tableau(fields)
    but = clean_but(fields)
    chance = clean_chance(fields)
    difficulty = chance_to_difficulty(chance)
    gtype = detect_type(raw)

    img_path = find_image(slug, title)
    img_md = ""
    if img_path:
        img_md = (
            f"![Page du livre — {title}]({img_path})\n\n"
            f"*Illustration extraite de Garraud 1984, p. {book_page or '?'}.*\n"
        )

    # Short rule
    short_lines = ["### Matériel", "", f"{cards} cartes. Patience à un joueur."]
    if ordre:
        short_lines += ["", "### Ordre des cartes", "", ordre]
    if tableau:
        short_lines += ["", "### Tableau", "", tableau]
    short_lines += ["", "### But", "", but or "Voir la version longue."]
    if chance:
        short_lines += ["", "### Chance de réussite", "", chance]
    short_lines += ["", "### Déroulement", "", procedure_p or "Voir la version longue."]
    short_body = "\n".join(short_lines)

    # Long version
    long_parts = []
    if intro:
        long_parts += ["### Présentation", "", intro, ""]
    long_parts += [
        "### Matériel",
        "",
        f"- **Cartes :** {cards}",
        f"- **Joueurs :** 1 (patience)",
        "",
    ]
    if ordre:
        long_parts += ["### Ordre des cartes", "", ordre, ""]
    if tableau:
        long_parts += ["### Tableau", "", tableau, ""]
    long_parts += ["### But du jeu", "", but or "Voir ci-dessus.", ""]
    if chance:
        long_parts += ["### Chance de réussite", "", chance, ""]
    long_parts += [
        "### Déroulement complet",
        "",
        procedure_p or "Se reporter au texte source.",
        "",
    ]
    if exemples_p:
        long_parts += ["### Exemple", "", exemples_p, ""]
    if conseils_p:
        long_parts += ["### Conseils", "", conseils_p, ""]
    if variants_p:
        long_parts += ["### Variantes", "", variants_p, ""]

    md = [
        f"# {title}",
        "",
        f"**Type :** Réussite ({gtype})",
        f"**Cartes :** {cards}",
        f"**Difficulté :** {difficulty}" + (f" ({chance})" if chance else ""),
        f"**But :** {but or 'Voir la règle détaillée.'}",
        "",
        "---",
        "",
    ]
    if img_md:
        md += ["## Illustration", "", img_md.rstrip(), "", "---", ""]
    md += [
        "## Règle courte",
        "",
        short_body.strip(),
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
        "",
    ]
    return "\n".join(md)


def main():
    if not QUEUE_PATH.exists():
        raise SystemExit(f"Missing {QUEUE_PATH}")
    queue_payload = json.loads(QUEUE_PATH.read_text(encoding="utf-8"))
    items = (queue_payload["items"] if isinstance(queue_payload, dict)
             else queue_payload)
    print(f"Queue: {len(items)} réussites")

    written = []
    errors = []
    for i, it in enumerate(items):
        slug = it["slug"]
        src = SECTIONS / f"{slug}.txt"
        if not src.exists():
            errors.append((slug, "missing source"))
            continue
        raw = src.read_text(encoding="utf-8", errors="replace")
        try:
            md = build_markdown(it, raw)
            (OUT / f"{slug}.md").write_text(md, encoding="utf-8")
            written.append({
                "title": nice_title(it["title"]),
                "slug": slug,
                "book_page": it.get("book_page"),
                "pdf_page": it.get("pdf_page"),
                "chars": len(md),
                "kind": "reussite",
            })
        except Exception as e:
            errors.append((slug, str(e)))

        if (i + 1) % 25 == 0:
            print(f"... {i+1}/{len(items)}  md={len(list(OUT.glob('*.md')))}")

    (OUT / "_MANIFEST_REUSSITES.json").write_text(
        json.dumps(written, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print(f"Done. written={len(written)} errors={len(errors)} "
          f"total md={len(list(OUT.glob('*.md')))}")
    if errors[:5]:
        print("errors sample:", errors[:5])


if __name__ == "__main__":
    main()
