#!/usr/bin/env python3
"""Smoke test for the protected destinations editor on the local PHP preview."""
import http.cookiejar
import sqlite3
import urllib.error
import re
import urllib.parse
import urllib.request
import os
from pathlib import Path

site = os.environ.get('EL_DESTINATIONS_TEST_BASE', 'http://127.0.0.1:8014/v1/').rstrip('/') + '/'
base = site + 'admin/'
cookies = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
try:
    opener.open(base + 'destinos.php')
    raise AssertionError('Editor must reject unauthenticated visitors')
except urllib.error.HTTPError as error:
    assert error.code == 403
try:
    opener.open(site + 'destinos/jerico/?preview=1')
    raise AssertionError('Private preview must reject unauthenticated visitors')
except urllib.error.HTTPError as error:
    assert error.code == 403
login = opener.open(base + 'index.php').read().decode()
csrf = re.search(r'name="csrf" value="([^"]+)"', login).group(1)
body = urllib.parse.urlencode({'csrf': csrf, 'action': 'login', 'username': 'localadmin', 'password': 'DestinosLocal8014!'}).encode()
response = opener.open(urllib.request.Request(base + 'index.php', data=body))
assert response.status == 200
try:
    opener.open(urllib.request.Request(base + 'destinos.php', data=urllib.parse.urlencode({'csrf':'invalid','action':'save'}).encode()))
    raise AssertionError('Editor writes must require a valid CSRF token')
except urllib.error.HTTPError as error:
    assert error.code == 403
editor = opener.open(base + 'destinos.php').read().decode()
expected_count = int(os.environ.get('EL_DESTINATIONS_TEST_COUNT', '21'))
assert 'Administrar destinos' in editor and f'Destinos actuales <small>({expected_count})</small>' in editor
assert 'Nuevo municipio' in editor and 'pt-BR' in editor and 'O subir una imagen nueva' in editor
assert 'los enlaces al mapa se crean automáticamente' in editor
assert 'Importar paquete ZIP' in editor and 'paquete-destino.php' in editor and 'Agregar atractivo' in editor and 'place_uploads' in editor
assert 'Importar JSON anterior' in editor and 'paquete_import_token' in editor
assert 'Traducciones generales, ubicación y referencias' in editor and 'Identificador URL (slug)' not in editor
db_path = Path(os.environ.get('EL_DESTINATIONS_TEST_DB', Path(__file__).resolve().parents[2] / '.local/destinos-dinamicos-preview.sqlite'))
with sqlite3.connect(db_path) as db:
    jerico_id = db.execute("SELECT id FROM destinations WHERE slug='jerico'").fetchone()[0]
jerico = opener.open(base + f'destinos.php?id={jerico_id}').read().decode()
assert 'Jericó' in jerico and 'name="slug"' in jerico and 'Atractivos turísticos' in jerico
preview = opener.open(site + 'destinos/jerico/?preview=1').read().decode()
assert 'Vista previa privada' in preview and 'noindex,nofollow' in preview
print(f'OK: una visita anónima se bloquea; el login abre el editor y muestra el catálogo de {expected_count} destinos.')
