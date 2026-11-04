#!/usr/bin/env python3
"""Build a coverage report from public dev1 pages, without changing dev1."""
import concurrent.futures
import datetime
import html
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import sys
sys.path.pop(0)  # Avoid a neighboring script named select.py shadowing the standard library.
import subprocess
import urllib.request

HERE = Path(__file__).resolve().parent
old = (HERE / 'cobertura-38-referencia.html').read_text()
rows = re.findall(r'<tr><th><a href="http://localhost:8086/lugares/([^/]+)/">(.*?)</a></th>(.*?)</tr>', old)
places = {slug: (html.unescape(name), '38') for slug, name, _ in rows}
repo = HERE.parents[3]
raw = subprocess.check_output(['git', 'show', 'contenido/lote-30-reproducible-20261104:content/place-batches/2026-11-04-30/evidence/directory-audit.json'], cwd=repo)
for item in json.loads(raw)['places']:
    slug = item['slug']
    if slug in places:
        raise RuntimeError('Municipio duplicado entre lotes: ' + slug)
    places[slug] = (slug.replace('-', ' ').title(), '30')
if len(places) != 68:
    raise RuntimeError(f'Se esperaban 68 lugares; encontrados {len(places)}')

class Page(HTMLParser):
    def __init__(self):
        super().__init__(); self.cards = {}; self.h1 = False; self.name = ''
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'h1': self.h1 = True
        if tag == 'article' and a.get('data-business-id'):
            self.cards[a['data-business-id']] = a.get('data-categories', a.get('data-category', '')).split(',')
    def handle_endtag(self, tag):
        if tag == 'h1': self.h1 = False
    def handle_data(self, data):
        if self.h1: self.name += data

def fetch(item):
    slug, (name, batch) = item
    try:
        with urllib.request.urlopen('http://127.0.0.1:8086/lugares/' + slug + '/', timeout=15) as response:
            page = Page(); page.feed(response.read().decode('utf-8'))
        cats = {}
        for values in page.cards.values():
            for cat in values:
                cat = cat.strip()
                if cat: cats[cat] = cats.get(cat, 0) + 1
        if batch == '30' and page.name:
            name = page.name.split('.')[0].strip()
        return dict(slug=slug, name=name, batch=batch, total=len(page.cards), cats=cats)
    except Exception as error:
        return dict(slug=slug, name=name, batch=batch, total=None, cats={}, error=str(error))

with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
    result = list(pool.map(fetch, places.items()))
result.sort(key=lambda r: (r['total'] is None, r['total'] if r['total'] is not None else 0, r['name'].casefold()))
categories = ['hospedaje','alimentacion','comercio','salud','servicios','vida-nocturna','cultura','experiencias','transporte','licores']
labels = ['Hospedaje','Alimentación','Comercio','Salud','Servicios','Vida nocturna','Cultura','Experiencias','Transporte','Licores']
stamp = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=-5))).strftime('%d/%m/%Y %H:%M')
out = ['<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cobertura de negocios · 68 lugares</title><style>body{font:16px system-ui;margin:30px;color:#173b2c;background:#f5f7f4}table{border-collapse:collapse;background:white;width:100%}td,th{border-bottom:1px solid #ddd;padding:10px;text-align:left;font-size:14px}thead{background:#dceade}.scroll{overflow:auto}.note{background:#fff0cb;padding:15px}a{color:#1b6545}td:nth-child(2){font-weight:bold}</style><h1>Cobertura de negocios · 68 lugares</h1>']
out.append(f'<p>Ordenados de menor a mayor por negocios visibles en dev1. Consulta: {stamp} (hora de Colombia).</p><p class="note">Son fichas visibles, no una confirmación de que cada negocio siga abierto. Una ficha puede pertenecer a varias categorías; las categorías no siempre suman el total. Un guion indica que la página no pudo consultarse.</p><div class="scroll"><table><thead><tr><th>Municipio</th><th>Total visible</th>')
out.extend('<th>'+html.escape(label)+'</th>' for label in labels)
out.append('<th>Lote</th></tr></thead><tbody>')
for row in result:
    out.append('<tr><th><a href="http://localhost:8086/lugares/'+html.escape(row['slug'])+'/">'+html.escape(row['name'])+'</a></th><td>'+('—' if row['total'] is None else str(row['total']))+'</td>')
    out.extend('<td>'+('—' if row['total'] is None else str(row['cats'].get(cat,0)))+'</td>' for cat in categories)
    out.append('<td>'+row['batch']+'</td></tr>')
out.append('</tbody></table></div></html>')
(HERE/'cobertura-68.html').write_text(''.join(out), encoding='utf-8')
(HERE/'cobertura-dev1.html').write_text(''.join(out), encoding='utf-8')
(HERE/'cobertura-68.json').write_text(json.dumps({'consultado':stamp,'lugares':result},ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'lugares':len(result),'consultados':sum(r['total'] is not None for r in result),'total_visible':sum(r['total'] or 0 for r in result),'errores':[(r['slug'],r.get('error')) for r in result if r['total'] is None]},ensure_ascii=False))
