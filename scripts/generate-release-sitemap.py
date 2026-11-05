#!/usr/bin/env python3
"""Inventario SEO de HTML final: ejecutar después de aplicar las integraciones."""
import argparse
import json
from pathlib import Path
from html.parser import HTMLParser
from xml.etree import ElementTree as ET
from public_page_routes import PREFIXES, ROUTES, php_page_exists

class Signals(HTMLParser):
    def __init__(self):
        super().__init__(); self.canonical = None; self.noindex = False
    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'meta' and values.get('name', '').lower() == 'robots':
            self.noindex |= 'noindex' in values.get('content', '').lower()
        if tag == 'link' and values.get('rel', '').lower() == 'canonical':
            self.canonical = values.get('href')

def generate(public, catalog):
    urls = set()
    for page in public.rglob('*.html'):
        relative = page.relative_to(public).as_posix()
        if any(part in {'admin', 'api', 'business'} for part in page.relative_to(public).parts):
            continue
        signals = Signals(); signals.feed(page.read_text())
        expected = 'https://explorandolugares.com/' + relative.removesuffix('index.html')
        if not signals.noindex and signals.canonical == expected and page.name == 'index.html':
            urls.add(expected)
    # Canonical component routes do not need a duplicate HTML file in the release.
    for prefix in PREFIXES:
        for route in ROUTES:
            if not php_page_exists(public, prefix, route):
                raise ValueError('Falta la página PHP canónica: /' + prefix + route)
            urls.add('https://explorandolugares.com/' + prefix + route)
    places = json.loads(catalog.read_text())['public_places']
    if len(places) != len(set(places)):
        raise ValueError('El catálogo contiene lugares publicados duplicados.')
    for prefix in PREFIXES:
        for slug in places:
            urls.add('https://explorandolugares.com/' + prefix + 'lugares/' + slug + '/')
    ET.register_namespace('', 'http://www.sitemaps.org/schemas/sitemap/0.9')
    root = ET.Element('{http://www.sitemaps.org/schemas/sitemap/0.9}urlset')
    for url in sorted(urls):
        ET.SubElement(ET.SubElement(root, 'url'), 'loc').text = url
    ET.indent(root)
    ET.ElementTree(root).write(public / 'sitemap.xml', encoding='UTF-8', xml_declaration=True)
    return len(urls)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--public', type=Path, required=True)
    parser.add_argument('--catalog', type=Path, required=True)
    args = parser.parse_args()
    print(f'Sitemap final: {generate(args.public, args.catalog)} URL canónicas.')
