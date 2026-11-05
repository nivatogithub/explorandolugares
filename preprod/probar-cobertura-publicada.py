#!/usr/bin/env python3
"""Comprueba guías específicas contra el servicio real de preproducción."""
import json
import urllib.request

BASE = 'http://127.0.0.1:8088'
EXPECTED = {'buga': ('76111', 'municipality'), 'capurgana': ('27006', 'other'), 'palomino': ('44090', 'other')}

with urllib.request.urlopen(BASE + '/api/coverage.php', timeout=15) as response:
    coverage = json.load(response)
assert coverage['publication_source_available'], 'La base de publicaciones no está disponible.'
places = {place['url']: place for department in coverage['departments'] for place in department['places'] if place.get('url')}
for slug, (code, kind) in EXPECTED.items():
    path = '/lugares/' + slug + '/'
    place = places.get(path)
    assert place and place['status'] == 'published' and place['municipality_code'] == code and place['type'] == kind, f'Cobertura incorrecta: {slug}'
    for url in (path, '/api/coverage.php?destination=' + slug):
        with urllib.request.urlopen(BASE + url, timeout=15) as response:
            assert response.status == 200, f'No carga {url}'
print('OK: Buga, Capurganá y Palomino disponibles con municipio y tipo correctos en preproducción.')
