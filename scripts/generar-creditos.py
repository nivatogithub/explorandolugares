#!/usr/bin/env python3
"""Crea el catálogo visual de créditos y enlaza a él desde cada footer."""
from pathlib import Path
from html import escape
import json
import os
import re

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / "ensayos/v1"
PHOTOS = json.loads((ROOT / "content/destinos-fotografias.json").read_text())
DESTINATIONS = {d["slug"]: d for d in json.loads((ROOT / "content/destinos-piloto.json").read_text())["destinations"]}

# Jericó precede al piloto y conserva su inventario editorial propio.
JERICO_PHOTOS = [
    {"file": "assets/jerico.webp", "caption": {"es": "Centro histórico de Jericó", "en": "Jericó historic centre"}, "author": "Xemenendura", "source": "https://commons.wikimedia.org/wiki/File:El_parque_Jeric%C3%B3.jpg", "license": "CC BY-SA 4.0", "licenseUrl": "https://creativecommons.org/licenses/by-sa/4.0/"},
    {"file": "assets/jerico/casa-santa-laura.webp", "caption": {"es": "Casa natal de Santa Laura", "en": "Saint Laura’s birthplace"}, "author": "Xemenendura", "source": "https://commons.wikimedia.org/wiki/File:Casa_de_la_beata_Laura.jpg", "license": "CC BY-SA 4.0", "licenseUrl": "https://creativecommons.org/licenses/by-sa/4.0/"},
    {"file": "assets/jerico/monumento-santa-laura.webp", "caption": {"es": "Monumento a Santa Laura", "en": "Saint Laura monument"}, "author": "Raomir Ramirez Morales", "source": "https://commons.wikimedia.org/wiki/File:Santa_Laura_Montoya_Upegui%2C_Jeric%C3%B3_Antioquia.jpg", "license": "CC BY-SA 3.0", "licenseUrl": "https://creativecommons.org/licenses/by-sa/3.0/"},
    {"file": "assets/jerico/cristo-salvador-directo.webp", "caption": {"es": "Cristo del Cerro El Salvador", "en": "Christ statue on El Salvador Hill"}, "author": "Xemenendura", "source": "https://commons.wikimedia.org/wiki/File:Cristo_en_Jeric%C3%B3.jpg", "license": "CC BY-SA 4.0", "licenseUrl": "https://creativecommons.org/licenses/by-sa/4.0/"},
    {"file": "assets/jerico/iglesia-rosada.webp", "caption": {"es": "Santuario de Santa Laura", "en": "Saint Laura Sanctuary"}, "author": "Angel OLMEDO", "source": "https://commons.wikimedia.org/wiki/File:Jeric%C3%B3,_santuario_Madre_Laura.jpg", "license": "Public domain", "licenseUrl": "https://creativecommons.org/publicdomain/mark/1.0/"},
]

PORTAL_ART = [
    ("mundo-explorando-ilustracion-v1.webp", "Mundo Explorando: pueblo", "Exploring Places: town"),
    ("mundo-explorando-ciudad-v2.webp", "Mundo Explorando: ciudad", "Exploring Places: city"),
    ("naturaleza-aventura-concepto-v2.webp", "Naturaleza y aventura", "Nature and adventure"),
    ("vida-local-concepto-v1.webp", "Vida local", "Local life"),
    ("negocios-locales-editorial-v1.webp", "Negocios locales", "Local businesses"),
    ("planes-negocio-editorial-v1.webp", "Planes para negocios", "Business plans"),
    ("proceso-negocio-editorial-v1.webp", "Proceso de participación", "Participation process"),
    ("proceso-publicacion-editorial-v1.webp", "Proceso de publicación", "Publishing process"),
    ("valor-negocios-editorial-v1.webp", "Valor para negocios", "Value for businesses"),
    ("impulsor-local-editorial-v1.webp", "Impulso local", "Local growth"),
]


def asset_path(file, english):
    return ("../../" if english else "../") + file


def photo_card(photo, english):
    caption = photo["caption"]["en" if english else "es"]
    author_label = "Author" if english else "Autor"
    source_label = "Original source" if english else "Fuente original"
    return f'''<article class="credit-card"><img src="{escape(asset_path(photo["file"], english))}" alt="{escape(caption)}" width="{photo.get("width", 1200)}" height="{photo.get("height", 800)}" loading="lazy" decoding="async"><div><h3>{escape(caption)}</h3><p><strong>{author_label}:</strong> {escape(photo["author"])}</p><p><a href="{escape(photo["source"])}" target="_blank" rel="noopener noreferrer">{source_label} ↗</a> · <a href="{escape(photo["licenseUrl"])}" target="_blank" rel="noopener noreferrer">{escape(photo["license"])}</a></p></div></article>'''


def illustration_card(item, english):
    filename, spanish, translated = item
    title = translated if english else spanish
    credit = "Original editorial illustration by Explorando Lugares, 2026." if english else "Ilustración editorial propia de Explorando Lugares, 2026."
    return f'''<article class="credit-card"><img src="{escape(asset_path("assets/" + filename, english))}" alt="{escape(title)}" width="1536" height="1024" loading="lazy" decoding="async"><div><h3>{escape(title)}</h3><p>{credit}</p></div></article>'''


def credits_page(english=False):
    lang = "en" if english else "es"
    title = "Image credits" if english else "Créditos de imágenes"
    intro = "This visual catalogue identifies the editorial illustrations and photographs used in public destination guides. Each photograph includes its author, original source and licence." if english else "Este catálogo visual identifica las ilustraciones editoriales y las fotografías usadas en las guías públicas. Cada fotografía incluye autor, fuente original y licencia."
    art_heading = "Editorial illustrations" if english else "Ilustraciones editoriales"
    art_intro = "Created by Explorando Lugares for this portal." if english else "Creadas por Explorando Lugares para este portal."
    sections = [f'<section><h2>{art_heading}</h2><p>{art_intro}</p><div class="photo-credit-grid">{"".join(illustration_card(item, english) for item in PORTAL_ART)}</div></section>']
    photo_groups = {"jerico": JERICO_PHOTOS, **PHOTOS}
    for slug, photos in photo_groups.items():
        name = "Jericó" if slug == "jerico" else DESTINATIONS.get(slug, {}).get("name", slug)
        sections.append(f'<section><h2>{escape(name)}</h2><div class="photo-credit-grid">{"".join(photo_card(photo, english) for photo in photos)}</div></section>')
    assets = "../../assets" if english else "../assets"
    home = "../index.html"
    home_label = "Home" if english else "Inicio"
    skip = "Skip to content" if english else "Saltar al contenido"
    return f'''<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="index,follow"><title>{title} | Explorando Lugares</title><link rel="stylesheet" href="{assets}/style.css"><link rel="icon" href="{assets}/favicon.svg" type="image/svg+xml"></head><body><a class="skip" href="#contenido">{skip}</a><main id="contenido" class="wrap credits-page"><a class="brand" href="{home}" aria-label="Explorando Lugares, {home_label.lower()}"><img class="brand-logo" src="{assets}/brand/explorando-lugares-logo-v2-transparent.png" alt="" width="64" height="64"><span>Explorando <b>Lugares</b></span></a><p class="eyebrow">Explorando Lugares</p><h1>{title}</h1><p class="intro">{intro}</p>{"".join(sections)}</main><footer><div class="wrap"><span>Explorando Lugares</span><span><a href="{home}">{home_label}</a></span></div></footer></body></html>'''


def footer_link(page):
    english = "en" in page.relative_to(SITE).parts
    target = SITE / ("en/creditos-fotograficos/index.html" if english else "creditos-fotograficos/index.html")
    href = Path(os.path.relpath(target, page.parent)).as_posix()
    return href, ("Image credits" if english else "Créditos de imágenes")


def add_footer_link(source, href, label):
    source = re.sub(r'\s*·\s*<a href="[^"]*creditos-fotograficos/index\.html">(?:Créditos fotográficos|Photo credits|Créditos de imágenes|Image credits)</a>', "", source)
    link = f' · <a href="{href}">{label}</a>'
    marker = "</span></div></footer>"
    if marker in source:
        return source.replace(marker, link + marker, 1)
    return source.replace("</footer>", link + "</footer>", 1)


def generate():
    for english, path in ((False, SITE / "creditos-fotograficos/index.html"), (True, SITE / "en/creditos-fotograficos/index.html")):
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(credits_page(english))
    for page in SITE.rglob("*.html"):
        if "creditos-fotograficos" in page.parts:
            continue
        source = re.sub(r'<details class="photo-credits">.*?</details>', "", page.read_text(), flags=re.S)
        href, label = footer_link(page)
        page.write_text(add_footer_link(source, href, label))


if __name__ == "__main__":
    generate()
