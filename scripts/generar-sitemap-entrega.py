#!/usr/bin/env python3
"""Inventario SEO de HTML final: ejecutar después de aplicar las integraciones."""
import argparse
from pathlib import Path
from html.parser import HTMLParser
from xml.etree import ElementTree as ET

class Signals(HTMLParser):
    def __init__(self):
        super().__init__(); self.canonical = None; self.noindex = False
    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'meta' and values.get('name', '').lower() == 'robots':
            self.noindex |= 'noindex' in values.get('content', '').lower()
        if tag == 'link' and values.get('rel', '').lower() == 'canonical':
            self.canonical = values.get('href')

def generate(public):
    urls = set()
    for page in public.rglob('*.html'):
        relative = page.relative_to(public).as_posix()
        if any(part in {'admin', 'api', 'business'} for part in page.relative_to(public).parts):
            continue
        signals = Signals(); signals.feed(page.read_text())
        expected = 'https://explorandolugares.com/' + relative.removesuffix('index.html')
        if not signals.noindex and signals.canonical == expected and page.name == 'index.html':
            urls.add(expected)
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
    print(f'Sitemap estático final: {generate(parser.parse_args().public)} URL canónicas.')
