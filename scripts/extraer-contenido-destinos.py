#!/usr/bin/env python3
"""Extrae una instantánea estructurada de las 63 guías HTML existentes.

Herramienta de migración editorial de una sola fuente. No modifica las páginas.
"""
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit
import json
import posixpath
import re
import unicodedata

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / "ensayos/v1"
LOCALES = {"es": "", "en": "en", "pt-BR": "pt-br"}
JERICO_GENERATED_MEDIA = {
    "assets/destinos/1/monumento-santa-laura-ilustrado-v2.webp": {
        "source": ".local/jerico-image-review/generated/monumento-santa-laura-ilustracion-v2.png",
        "width": 900, "height": 1200,
        "alt": {
            "es": "Monumento de bronce a Santa Madre Laura y una niña, en una ilustración editorial de tinta y gouache basada en una fotografía.",
            "en": "Bronze monument to Saint Mother Laura and a child, in an editorial ink-and-gouache illustration based on a photograph.",
            "pt-BR": "Monumento de bronze à Santa Madre Laura e uma menina, em ilustração editorial de tinta e guache baseada em uma fotografia."
        },
    },
    "assets/destinos/1/parque-principal-ilustrado.webp": {
        "source": ".local/jerico-image-review/generated/parque-principal-encuadre-candidato.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Parque principal y fachada del centro de Jericó, en ilustración editorial basada en una fotografía de referencia.",
            "en": "Jericó main square and historic façade, in an editorial illustration based on a reference photograph.",
            "pt-BR": "Parque principal e fachada do centro de Jericó, em ilustração editorial baseada em uma fotografia de referência."
        },
    },
    "assets/destinos/1/cristo-cerro-el-salvador-ilustrado-v2.webp": {
        "source": ".local/jerico-image-review/generated/cristo-rey-acercamiento-candidato.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Cristo del Cerro El Salvador visto de cerca sobre Jericó, en ilustración editorial basada en una fotografía de referencia.",
            "en": "Christ statue on El Salvador Hill seen closer above Jericó, in an editorial illustration based on a reference photograph.",
            "pt-BR": "Cristo do Cerro El Salvador visto de perto sobre Jericó, em ilustração editorial baseada em uma fotografia de referência."
        },
    },
    "assets/destinos/1/santuario-santa-laura-ilustrado-v2.webp": {
        "source": ".local/jerico-image-review/generated/santuario-fachada-completa-candidato.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Santuario de Santa Laura en Jericó, con su fachada rosada y palmas visibles en la entrada, en ilustración editorial basada en una fotografía de referencia.",
            "en": "Sanctuary of Saint Laura in Jericó, with its pink façade and palms visible at the entrance, in an editorial illustration based on a reference photograph.",
            "pt-BR": "Santuário de Santa Laura em Jericó, com fachada rosa e palmeiras visíveis na entrada, em ilustração editorial baseada em uma fotografia de referência."
        },
    },
    "assets/destinos/1/casa-santa-laura-ilustrada-v2.webp": {
        "source": ".local/jerico-image-review/generated/casa-santa-laura-ilustracion-v2.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Fachada de la casa natal de Santa Laura en Jericó, reinterpretada en tinta y gouache a partir de una fotografía de referencia.",
            "en": "Façade of Saint Laura's birthplace in Jericó, reinterpreted in ink and gouache from a reference photograph.",
            "pt-BR": "Fachada da casa natal de Santa Laura em Jericó, reinterpretada em tinta e guache a partir de uma fotografia de referência."
        },
    },
    "assets/destinos/1/jardin-botanico-los-balsos-ilustrado.webp": {
        "source": ".local/jerico-image-review/generated/jardin-botanico-los-balsos-ilustracion-v1.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Estanque, puente de madera y vegetación del Jardín Botánico Los Balsos, en una ilustración editorial basada en sus elementos documentados.",
            "en": "Pond, wooden bridge and vegetation at Los Balsos Botanical Garden, in an editorial illustration based on its documented features.",
            "pt-BR": "Lago, ponte de madeira e vegetação do Jardim Botânico Los Balsos, em uma ilustração editorial baseada em elementos documentados."
        },
    },
    "assets/destinos/1/cerro-de-las-nubes-sendero-ilustrado-v2.webp": {
        "source": ".local/jerico-image-review/generated/cerro-de-las-nubes-candidata-v2.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Ilustración editorial del sendero de piedra que asciende entre vegetación en el Cerro de las Nubes.",
            "en": "Editorial illustration of a stone trail climbing through vegetation on Cerro de las Nubes.",
            "pt-BR": "Ilustração editorial da trilha de pedra que sobe entre a vegetação no Cerro de las Nubes."
        },
        "provenance": {
            "reference_source_url": "https://es.wikiloc.com/rutas-senderismo/cerro-las-nubes-jerico-antioquia-130350353",
            "factual_basis_url": "https://es.wikiloc.com/rutas-senderismo/cerro-las-nubes-jerico-antioquia-130350353",
            "reference_type": "Textual description only; no source photograph was used as image input.",
            "used_reference_images": False,
            "generated_asset_id": "cerro-de-las-nubes-candidata-v2",
            "human_approval": "Owner approved the Cerro de las Nubes illustration for the destination card in chat on 2026-10-15."
        },
    },
    "assets/destinos/1/chorros-quebradona-ilustrado-v2.webp": {
        "source": ".local/jerico-image-review/generated/chorros-quebradona-candidata-v2.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Ilustración editorial de una cascada modesta entre roca y vegetación en Chorros de Quebradona.",
            "en": "Editorial illustration of a modest waterfall among rocks and vegetation at Chorros de Quebradona.",
            "pt-BR": "Ilustração editorial de uma pequena cachoeira entre pedras e vegetação em Chorros de Quebradona."
        },
        "provenance": {
            "reference_source_url": "https://es.wikiloc.com/rutas-senderismo/chorros-de-quebradona-jerico-155370461",
            "factual_basis_url": "https://www.corantioquia.gov.co/corantioquia-invita-a-descubrir-y-proteger-los-tesoros-naturales-de-su-jurisdiccion/",
            "reference_type": "Textual descriptions and route context only; no source photograph was used as image input.",
            "used_reference_images": False,
            "generated_asset_id": "chorros-quebradona-candidata-v2",
            "human_approval": "Owner approved the Chorros de Quebradona illustration for the destination card in chat on 2026-10-15."
        },
    },
    "assets/destinos/1/helicoptero-medellin-jerico-ilustracion.webp": {
        "source": ".local/jerico-experience-candidates/helicoptero-medellin-jerico.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Ilustración editorial de un vuelo en helicóptero sobre montañas verdes; representa la actividad de forma general.",
            "en": "Editorial illustration of a helicopter flight over green mountains, representing the activity in general.",
            "pt-BR": "Ilustração editorial de um voo de helicóptero sobre montanhas verdes; representa a atividade de forma geral."
        },
    },
    "assets/destinos/1/parapente-puerto-arturo-ilustracion.webp": {
        "source": ".local/jerico-experience-candidates/parapente-puerto-arturo.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Ilustración editorial de un vuelo biplaza en parapente sobre montañas; no representa un punto específico de despegue o aterrizaje.",
            "en": "Editorial illustration of a tandem paraglider over mountains; it does not depict a specific launch or landing site.",
            "pt-BR": "Ilustração editorial de um voo duplo de parapente sobre montanhas; não representa um ponto específico de decolagem ou pouso."
        },
    },
    "assets/destinos/1/experiencia-finca-cafetera-ilustracion.webp": {
        "source": ".local/jerico-experience-candidates/experiencia-finca-cafetera.png",
        "width": 1100, "height": 825,
        "alt": {
            "es": "Ilustración editorial de cafetos con frutos maduros en una ladera de montaña; representa una experiencia cafetera de forma general.",
            "en": "Editorial illustration of coffee plants with ripe cherries on a mountain slope, representing a coffee experience in general.",
            "pt-BR": "Ilustração editorial de cafezais com frutos maduros em uma encosta; representa uma experiência cafeeira de forma geral."
        },
    },
}
JERICO_EXPERIENCES = {
    "helicoptero-medellin-jerico": {
        "url": "",
        "media": "assets/destinos/1/helicoptero-medellin-jerico-ilustracion.webp",
        "translations": {
            "es": ("Vuelo privado en helicóptero Medellín–Jericó", "Experiencia de vuelo que podría ofrecer un operador local. Se mostrará cuando exista un acuerdo confirmado y se validen ruta, condiciones y disponibilidad.", ""),
            "en": ("Private helicopter flight Medellín–Jericó", "A flight experience that may be offered by a local operator. It will appear once an agreement is confirmed and route, conditions and availability are verified.", ""),
            "pt-BR": ("Voo privativo de helicóptero Medellín–Jericó", "Experiência de voo que poderá ser oferecida por um operador local. Será exibida quando houver um acordo confirmado e rota, condições e disponibilidade estiverem verificadas.", ""),
        },
    },
    "parapente-puerto-arturo": {
        "url": "",
        "media": "assets/destinos/1/parapente-puerto-arturo-ilustracion.webp",
        "translations": {
            "es": ("Parapente biplaza en Jericó", "Experiencia de parapente que podría ofrecer un operador local. Se mostrará cuando exista un acuerdo confirmado y se validen despegue, aterrizaje, condiciones y disponibilidad.", ""),
            "en": ("Tandem paragliding in Jericó", "A paragliding experience that may be offered by a local operator. It will appear once an agreement is confirmed and launch, landing, conditions and availability are verified.", ""),
            "pt-BR": ("Voo duplo de parapente em Jericó", "Experiência de parapente que poderá ser oferecida por um operador local. Será exibida quando houver um acordo confirmado e decolagem, pouso, condições e disponibilidade estiverem verificados.", ""),
        },
    },
    "experiencia-finca-cafetera": {
        "url": "",
        "media": "assets/destinos/1/experiencia-finca-cafetera-ilustracion.webp",
        "translations": {
            "es": ("Experiencia en finca cafetera", "Experiencia para conocer el cultivo y el proceso del café en una finca de Jericó. Se mostrará cuando se confirme el lugar y exista un acuerdo con quien la ofrece.", ""),
            "en": ("Coffee farm experience", "An experience to learn about coffee growing and processing at a farm in Jericó. It will appear once the location is verified and an agreement with its provider is confirmed.", ""),
            "pt-BR": ("Experiência em fazenda de café", "Experiência para conhecer o cultivo e o processamento do café em uma fazenda de Jericó. Será exibida quando o local for confirmado e houver acordo com quem a oferece.", ""),
        },
    },
}
JERICO_WATERFALL = {
    "slug": "chorros-de-quebradona",
    "media": "assets/destinos/1/chorros-quebradona-ilustrado-v2.webp",
    "map_url": "https://www.google.com/maps/search/?api=1&query=Chorros+de+Quebradona+Jeric%C3%B3+Antioquia+Colombia",
}


class Node:
    def __init__(self, tag, attrs=None):
        self.tag = tag
        self.attrs = dict(attrs or [])
        self.children = []

    def text(self):
        return "".join(c.text() if isinstance(c, Node) else c for c in self.children)

    def find(self, tag=None, *, class_name=None, id_name=None, recursive=True):
        for child in self.children:
            if not isinstance(child, Node):
                continue
            classes = child.attrs.get("class", "").split()
            if ((tag is None or child.tag == tag)
                    and (class_name is None or class_name in classes)
                    and (id_name is None or child.attrs.get("id") == id_name)):
                return child
            if recursive:
                found = child.find(tag, class_name=class_name, id_name=id_name)
                if found:
                    return found
        return None

    def find_all(self, tag=None, *, class_name=None, id_name=None, recursive=True):
        found = []
        for child in self.children:
            if not isinstance(child, Node):
                continue
            classes = child.attrs.get("class", "").split()
            if ((tag is None or child.tag == tag)
                    and (class_name is None or class_name in classes)
                    and (id_name is None or child.attrs.get("id") == id_name)):
                found.append(child)
            if recursive:
                found.extend(child.find_all(tag, class_name=class_name, id_name=id_name))
        return found


class Tree(HTMLParser):
    VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.root = Node("document")
        self.stack = [self.root]

    def handle_starttag(self, tag, attrs):
        node = Node(tag, attrs)
        self.stack[-1].children.append(node)
        if tag not in self.VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.stack[-1].children.append(Node(tag, attrs))

    def handle_endtag(self, tag):
        for pos in range(len(self.stack) - 1, 0, -1):
            if self.stack[pos].tag == tag:
                del self.stack[pos:]
                return

    def handle_data(self, data):
        self.stack[-1].children.append(data)


def parse(path):
    tree = Tree()
    tree.feed(path.read_text())
    return tree.root


def clean(node):
    return re.sub(r"\s+", " ", node.text()).strip() if node else ""


def direct(node, tag, class_name=None):
    return [c for c in node.children if isinstance(c, Node) and c.tag == tag
            and (class_name is None or class_name in c.attrs.get("class", "").split())]


def image_info(img, page):
    src = img.attrs.get("src", "")
    path = posixpath.normpath(posixpath.join(page.parent.relative_to(SITE).as_posix(), src))
    if not path.startswith("assets/"):
        raise ValueError(f"Ruta de imagen fuera de assets: {page}: {src}")
    local = (SITE / path).resolve()
    if not local.is_relative_to((SITE / "assets").resolve()) or not local.is_file():
        raise ValueError(f"Imagen ausente o fuera de assets: {page}: {src}")
    return {"path": path, "alt": img.attrs.get("alt", ""),
            "width": int(img.attrs["width"]) if img.attrs.get("width", "").isdigit() else None,
            "height": int(img.attrs["height"]) if img.attrs.get("height", "").isdigit() else None}


def parse_page(page):
    tree = parse(page)
    head = tree.find("head")
    main = tree.find("main", class_name="destination-page")
    if not head or not main:
        raise ValueError(f"Estructura inesperada: {page}")
    meta = next((n for n in head.find_all("meta") if n.attrs.get("name") == "description"), None)
    title = tree.find("title")
    hero = main.find("section", class_name="destination-hero")
    overview = main.find("section", id_name="descubre")
    highlights = main.find("section", id_name="que-conocer")
    directory = main.find("section", id_name="lugares")
    if not all([hero, overview, highlights, directory]):
        raise ValueError(f"Falta sección editorial requerida: {page}")
    hero_copy = next(n for n in hero.children if isinstance(n, Node) and n.tag == "div")
    heading = hero_copy.find("h1")
    breadcrumb = next((n for n in main.find_all("span") if n.attrs.get("aria-current") == "page"), None)
    name = clean(breadcrumb)
    if not name:
        raise ValueError(f"No se encontró el nombre en breadcrumb: {page}")
    headline = clean(heading.find("em"))
    intro = clean(hero_copy.find("p", class_name="intro"))
    eyebrow = clean(hero_copy.find("p", class_name="eyebrow"))
    history_div = next(n for n in overview.children if isinstance(n, Node) and n.tag == "div")
    story = [clean(n) for n in direct(history_div, "p")
             if "eyebrow" not in n.attrs.get("class", "").split()
             and "credit" not in n.attrs.get("class", "").split() and clean(n)]
    reasons_aside = overview.find("aside", class_name="destination-reasons")
    reasons = [clean(n) for n in reasons_aside.find_all("li")] if reasons_aside else []
    history_figure = overview.find("figure", class_name="destination-history-photo")
    hero_figure = hero.find("figure")
    if not hero_figure:
        raise ValueError(f"Falta imagen principal: {page}")
    hero_img = hero_figure.find("img")
    hero_media = image_info(hero_img, page)
    hero_media["caption"] = clean(hero_figure.find("figcaption"))
    history_media = None
    if history_figure:
        image = history_figure.find("img")
        history_media = image_info(image, page)
        history_media["caption"] = clean(history_figure.find("figcaption"))
    places = []
    grid = highlights.find("div", class_name="highlight-grid")
    for article in grid.find_all("article") if grid else []:
        h3 = article.find("h3")
        desc = next((n for n in direct(article, "p") if clean(n)), None)
        map_link = next((n for n in article.find_all("a")
                         if "map" in clean(n).lower() or "mapa" in clean(n).lower()), None)
        img = article.find("img")
        media = image_info(img, page) if img else None
        if media:
            media["caption"] = media["alt"]
        if not h3 or not desc or not map_link:
            raise ValueError(f"Tarjeta de atractivo incompleta: {page}")
        places.append({"slug": slugify(clean(h3)), "name": clean(h3), "description": clean(desc),
                       "map_url": map_link.attrs.get("href", ""), "media": media})
    extra = highlights.find("div", class_name="highlight-extra")
    if extra:
        h3 = extra.find("h3")
        desc = extra.find("p")
        map_link = next((n for n in extra.find_all("a") if "maps/search" in n.attrs.get("href", "")), None)
        if h3 and desc and map_link:
            places.append({"slug": slugify(clean(h3)), "name": clean(h3), "description": clean(desc),
                           "map_url": map_link.attrs.get("href", ""), "media": None})
    sources = []
    for section_key, section in (("history", overview), ("places", highlights)):
        for link in section.find_all("a"):
            href = link.attrs.get("href", "")
            if (urlsplit(href).scheme == "https" and "google.com/maps" not in href
                    and "maps/search" not in href):
                pair = {"url": href, "label": clean(link), "section_key": section_key}
                if pair not in sources:
                    sources.append(pair)
    return {"name": name, "title": clean(title), "meta_description": meta.attrs.get("content", "") if meta else "",
            "eyebrow": eyebrow, "headline": headline, "intro": intro, "history": story,
            "reasons": reasons, "hero_media": hero_media, "history_media": history_media,
            "places": places, "sources": sources}


def media_credits():
    records = {}
    for destinations in json.loads((ROOT / "content/destinos-fotografias.json").read_text()).values():
        for row in destinations:
            records[row["file"]] = {"author": row["author"], "source_url": row["source"],
                "license_name": row["license"], "license_url": row["licenseUrl"].replace("http://creativecommons.org/", "https://creativecommons.org/")}
    for row in [
        {"file": "assets/jerico.webp", "author": "Xemenendura", "source_url": "https://commons.wikimedia.org/wiki/File:El_parque_Jeric%C3%B3.jpg", "license_name": "CC BY-SA 4.0", "license_url": "https://creativecommons.org/licenses/by-sa/4.0/"},
    ]:
        records[row["file"]] = {k: v for k, v in row.items() if k != "file"}
    jerico = json.loads((ROOT / "content/jerico-fotografias.json").read_text())
    used = {
        "assets/jerico/casa-santa-laura.webp": 0,
        "assets/jerico/iglesia-rosada.webp": 1,
        "assets/jerico/monumento-santa-laura.webp": 2,
        "assets/jerico/cristo-salvador-directo.webp": 3,
    }
    for path, index in used.items():
        row = jerico[index]
        metadata = row.get("metadata", {})
        artist = metadata.get("Artist", {}).get("value", "")
        artist = re.sub(r"<[^>]*>", "", artist).strip() or metadata.get("Credit", {}).get("value", "")
        artist = re.sub(r"<[^>]*>", "", artist).strip()
        raw_license = metadata.get("License", {}).get("value", "")
        license_name = {"cc-by-sa-3.0": "CC BY-SA 3.0", "cc-by-sa-4.0": "CC BY-SA 4.0", "pd": "Public domain"}.get(raw_license.lower(), raw_license)
        license_url = metadata.get("LicenseUrl", {}).get("value", "")
        if not license_url and license_name == "Public domain":
            license_url = "https://creativecommons.org/publicdomain/mark/1.0/"
        records[path] = {"author": artist, "source_url": row["source"],
                         "license_name": license_name, "license_url": license_url}
    return records


def slugify(value):
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode().lower()
    return re.sub(r"-+", "-", re.sub(r"[^a-z0-9]+", "-", value)).strip("-")


def main():
    registry = [{"slug": "jerico", "region": "Suroeste"}] + json.loads(
        (ROOT / "content/destinos-piloto.json").read_text())["destinations"]
    publication = json.loads((ROOT / "content/estado-publicacion-destinos.json").read_text())
    registry_slugs = {destination["slug"] for destination in registry}
    if set(publication) != registry_slugs or any(status not in {"published", "draft"} for status in publication.values()):
        raise ValueError("El estado de publicación debe definir cada destino piloto como published o draft")
    if {d["slug"] for d in registry} != {p.parent.name for p in (SITE / "destinos").glob("*/index.html")}:
        raise ValueError("El inventario de destino no coincide con las páginas del piloto")
    credits = media_credits()
    catalog_path = ROOT / "content/destinos-dinamicos.json"
    prior_provenance = {}
    if catalog_path.is_file():
        old_catalog = json.loads(catalog_path.read_text())
        prior_provenance = {row["path"]: row["provenance"] for row in old_catalog.get("media", [])
                            if row.get("provenance")}
    result = {"schema_version": 1, "locales": list(LOCALES), "destinations": []}
    used_media = set()
    for destination in registry:
        slug = destination["slug"]
        translations = {}
        base = None
        for locale, folder in LOCALES.items():
            page = SITE / folder / "destinos" / slug / "index.html"
            if not page.is_file():
                raise ValueError(f"Falta página {locale}: {page}")
            data = parse_page(page)
            if locale == "es":
                base = {"slug": slug, "destination_type": "municipality", "country_code": "CO",
                        "department": "Antioquia", "subregion": destination["region"],
                        "publication_status": publication[slug], "display_order": len(result["destinations"])+1}
            translations[locale] = data
            for record in [data["hero_media"], data["history_media"], *[p["media"] for p in data["places"]]]:
                if record:
                    used_media.add(record["path"])
        base["translations"] = translations
        result["destinations"].append(base)
    # Reemplaza en el catálogo dinámico fotos de Jericó por ilustraciones
    # editoriales locales basadas en referencias y añade experiencias con enlaces
    # a operadores; las páginas estáticas se mantienen como fuente editorial.
    jerico = next(d for d in result["destinations"] if d["slug"] == "jerico")
    for locale, tr in jerico["translations"].items():
        official_source_labels = {
            "es": "Atractivos de Jericó · Gobernación de Antioquia ↗",
            "en": "Jericó attractions · Government of Antioquia ↗",
            "pt-BR": "Atrações de Jericó · Governo de Antioquia ↗",
        }
        official_source = {"url": "https://www.antioquia.gov.co/images/img/turismo/2025/los-imperdibles-de-suroeste-28-30-junio.pdf",
                           "label": official_source_labels[locale], "section_key": "places"}
        if official_source not in tr["sources"]:
            tr["sources"].insert(0, official_source)
        for target, path in [(tr["hero_media"], "assets/destinos/1/parque-principal-ilustrado.webp"),
                             (tr["history_media"], "assets/destinos/1/monumento-santa-laura-ilustrado-v2.webp"),
                             (tr["places"][0]["media"], "assets/destinos/1/parque-principal-ilustrado.webp"),
                             (tr["places"][1]["media"], "assets/destinos/1/cristo-cerro-el-salvador-ilustrado-v2.webp"),
                             (tr["places"][2]["media"], "assets/destinos/1/santuario-santa-laura-ilustrado-v2.webp"),
                             (tr["places"][3]["media"], "assets/destinos/1/casa-santa-laura-ilustrada-v2.webp")]:
            target["path"] = path
            target["width"] = JERICO_GENERATED_MEDIA[path]["width"]
            target["height"] = JERICO_GENERATED_MEDIA[path]["height"]
            target["alt"] = JERICO_GENERATED_MEDIA[path]["alt"][locale]
            target["caption"] = target["alt"]
        garden = tr["places"][4]
        garden_media_path = "assets/destinos/1/jardin-botanico-los-balsos-ilustrado.webp"
        garden_info = JERICO_GENERATED_MEDIA[garden_media_path]
        garden["media"] = {"path": garden_media_path, "width": garden_info["width"],
                           "height": garden_info["height"], "alt": garden_info["alt"][locale],
                           "caption": garden_info["alt"][locale]}
        las_nubes = tr["places"][5]
        nubes_path = "assets/destinos/1/cerro-de-las-nubes-sendero-ilustrado-v2.webp"
        nubes_info = JERICO_GENERATED_MEDIA[nubes_path]
        las_nubes["media"] = {"path": nubes_path, "width": nubes_info["width"],
                               "height": nubes_info["height"], "alt": nubes_info["alt"][locale],
                               "caption": nubes_info["alt"][locale]}
        waterfall = tr["places"][6]
        waterfall["slug"] = JERICO_WATERFALL["slug"]
        waterfall["map_url"] = JERICO_WATERFALL["map_url"]
        waterfall["description"] = {
            "es": "Atractivo natural rural de Jericó, mencionado como Chorros de Quebradona. Confirma con fuentes locales dónde inicia el recorrido y las condiciones actuales de acceso.",
            "en": "A rural natural attraction in Jericó, known as Chorros de Quebradona. Check with local sources for the trailhead and current access conditions.",
            "pt-BR": "Atrativo natural rural de Jericó, conhecido como Chorros de Quebradona. Confirme com fontes locais onde começa o percurso e as condições atuais de acesso."
        }[locale]
        waterfall_path = JERICO_WATERFALL["media"]
        waterfall_info = JERICO_GENERATED_MEDIA[waterfall_path]
        waterfall["media"] = {"path": waterfall_path, "width": waterfall_info["width"],
                               "height": waterfall_info["height"], "alt": waterfall_info["alt"][locale],
                               "caption": waterfall_info["alt"][locale]}
        for slug, experience in JERICO_EXPERIENCES.items():
            name, description, label = experience["translations"][locale]
            media_path = experience["media"]
            media_info = JERICO_GENERATED_MEDIA[media_path]
            media = {"path": media_path, "alt": media_info["alt"][locale],
                     "width": media_info["width"], "height": media_info["height"],
                     "caption": media_info["alt"][locale]}
            tr["places"].append({"slug": slug, "type": "experience", "publication_status": "draft", "name": name,
                "description": description, "map_url": "", "external_url": experience["url"],
                "external_label": label, "media": media})
    used_media = {media["path"] for d in result["destinations"] for tr in d["translations"].values()
                  for media in [tr["hero_media"], tr["history_media"], *[p["media"] for p in tr["places"]]] if media}
    all_media = {}
    for dest in result["destinations"]:
        slug = dest["slug"]
        for locale, data in dest["translations"].items():
            candidates = [("hero", data["hero_media"]), ("history", data["history_media"])]
            candidates.extend((f"poi:{i+1}", poi["media"]) for i, poi in enumerate(data["places"]))
            for role, media in candidates:
                if not media:
                    continue
                path = media["path"]
                generated = JERICO_GENERATED_MEDIA.get(path)
                row = all_media.setdefault(path, {"path": path, "origin": "ai_generated" if generated else "external",
                    "author": "Explorando Lugares" if generated else "", "creator": "Explorando Lugares" if generated else "",
                    "source_url": "", "license_name": "Ilustración editorial generada con IA" if generated else "",
                    "license_url": "", "width": media["width"], "height": media["height"],
                    "translations": {}, "placements": []})
                row["translations"][locale] = {"alt": media["alt"], "caption": media["caption"]}
                provenance = (generated.get("provenance") if generated else None) or prior_provenance.get(path)
                if provenance:
                    row["provenance"] = provenance
                placement = {"destination_slug": slug, "role": role}
                if placement not in row["placements"]:
                    row["placements"].append(placement)
    missing = []
    for path in used_media:
        if path in JERICO_GENERATED_MEDIA:
            continue
        if path not in credits:
            missing.append(path)
        else:
            all_media[path].update(credits[path])
    if missing:
        raise ValueError("Fotos usadas sin crédito/licencia completos: " + ", ".join(sorted(missing)))
    result["media"] = [all_media[key] for key in sorted(all_media)]
    # Validaciones de cobertura y conservación editorial de las páginas actuales.
    if len(result["destinations"]) != 21:
        raise ValueError(f"Se esperaban 21 destinos; hay {len(result['destinations'])}")
    if sum(len(d["translations"]["es"]["places"]) for d in result["destinations"]) != 70:
        raise ValueError("Se esperaban 70 recomendaciones: 67 lugares base y 3 experiencias de Jericó")
    for d in result["destinations"]:
        for locale in LOCALES:
            tr = d["translations"][locale]
            if not tr["headline"] or not tr["intro"] or not tr["history"] or len(tr["reasons"]) != 3:
                raise ValueError(f"Traducción incompleta: {d['slug']} / {locale}")
            if len(tr["places"]) != len(d["translations"]["es"]["places"]):
                raise ValueError(f"Cantidad de atractivos no coincide: {d['slug']} / {locale}")
    catalog_path.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
    print(f"OK: {len(result['destinations'])} destinos, 3 idiomas, 70 recomendaciones, {len(result['media'])} imágenes con procedencia.")


if __name__ == "__main__":
    main()
