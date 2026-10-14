#!/usr/bin/env python3
"""End-to-end image upload, draft preview, public-hidden, and cleanup test.

Requires the isolated local server from scripts/preview-destinos-dinamicos.sh.
"""
import http.cookiejar
import os
import re
import sqlite3
import struct
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zlib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = os.environ.get('EL_DESTINATIONS_TEST_BASE', 'http://127.0.0.1:8014/v1/').rstrip('/') + '/'
DB = Path(os.environ.get('EL_DESTINATIONS_TEST_DB', ROOT / '.local/destinos-dinamicos-preview.sqlite'))
MEDIA_ROOT = Path(os.environ.get('EL_DESTINATIONS_TEST_MEDIA_ROOT', ROOT / '.local/destinos-media'))
cookies = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
token = ''
destination_id = 0
slug = 'e2e-upload-' + uuid.uuid4().hex[:10]


def chunk(name, data):
    return struct.pack('!I', len(data)) + name + data + struct.pack('!I', zlib.crc32(name + data) & 0xffffffff)


def png_image():
    width, height = 240, 160
    row = b'\x00' + bytes([42, 110, 92]) * width
    raw = row * height
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('!IIBBBBB', width, height, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b''))


def multipart(fields, files):
    boundary = '----el-test-' + uuid.uuid4().hex
    body = bytearray()
    for name, value in fields.items():
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    for name, filename, content, mime in files:
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode())
        body.extend(content + b'\r\n')
    body.extend(f'--{boundary}--\r\n'.encode())
    return bytes(body), boundary


def post(path, fields, files=()):
    body, boundary = multipart(fields, files)
    req = urllib.request.Request(BASE + path, data=body, headers={'Content-Type': f'multipart/form-data; boundary={boundary}'})
    return opener.open(req)


try:
    login_page = opener.open(BASE + 'admin/index.php').read().decode()
    csrf = re.search(r'name="csrf" value="([^"]+)"', login_page).group(1)
    opener.open(urllib.request.Request(BASE + 'admin/index.php', data=urllib.parse.urlencode({
        'csrf': csrf, 'action': 'login', 'username': 'localadmin', 'password': 'DestinosLocal8014!'
    }).encode()))
    editor = opener.open(BASE + 'admin/destinos.php').read().decode()
    token = re.search(r'name="csrf" value="([^"]+)"', editor).group(1)

    fields = {
        'csrf': token, 'action': 'save', 'id': '', 'slug': slug,
        'destination_type': 'municipality', 'country_code': 'CO', 'department': 'Antioquia',
        'display_order': '999', 'publication_status': 'draft',
        'translations[es][display_name]': 'Prueba de carga local',
        'places[0][slug]': 'atractivo-de-prueba', 'places[0][publication_status]': 'draft',
        'places[0][translations][es][name]': 'Atractivo de prueba',
        'places[0][translations][es][description]': 'Descripción de prueba local.',
        'places[0][translations][en][name]': 'Test attraction',
        'places[0][translations][en][description]': 'Local test description.',
        'places[0][translations][pt-BR][name]': 'Atrativo de teste',
        'places[0][translations][pt-BR][description]': 'Descrição de teste local.',
        'places[0][image_origin]': 'ai_generated',
        'places[0][image_alt][es]': 'Imagen de prueba local',
        'places[0][image_alt][en]': 'Local test image',
        'places[0][image_alt][pt-BR]': 'Imagem de teste local',
        'image_origin': 'ai_generated',
        'image_alt[es]': 'Portada de prueba local',
        'image_alt[en]': 'Local test cover',
        'image_alt[pt-BR]': 'Capa de teste local',
    }
    image = png_image()
    files = [
        ('hero_upload', 'cover.png', image, 'image/png'),
        ('place_uploads[0]', 'attraction.png', image, 'image/png'),
    ]
    response = post('admin/destinos.php', fields, files)
    match = re.search(r'[?&]id=(\d+)', response.geturl())
    assert match, f'Draft save did not redirect to its editor: {response.geturl()}'
    destination_id = int(match.group(1))

    with sqlite3.connect(DB) as db:
        row = db.execute('SELECT publication_status FROM destinations WHERE id=?', (destination_id,)).fetchone()
        assert row == ('draft',), 'A newly created destination must remain a draft.'
        assets = db.execute('SELECT id,asset_path,origin FROM media_assets WHERE asset_path LIKE ?', (f'assets/destinos/{destination_id}/%',)).fetchall()
        assert len(assets) == 2 and all(asset[2] == 'ai_generated' for asset in assets), 'Both cover and attraction image metadata must be stored.'
        assert db.execute('SELECT COUNT(*) FROM destination_media WHERE destination_id=? AND role="hero"', (destination_id,)).fetchone()[0] == 1
        assert db.execute('SELECT COUNT(*) FROM point_of_interest_media pm JOIN points_of_interest p ON p.id=pm.point_of_interest_id WHERE p.destination_id=?', (destination_id,)).fetchone()[0] == 1
        asset_paths = [asset[1] for asset in assets]

    private_page = opener.open(BASE + f'destinos/{slug}/?preview=1').read().decode()
    assert 'Vista previa privada' in private_page and 'Atractivo de prueba' in private_page
    image_path = re.search(r'/assets/destinos/\d+/imagen-[a-f0-9]+\.png', private_page).group(0)
    assert opener.open(BASE.rstrip('/') + image_path).read() == image
    try:
        opener.open(BASE + f'destinos/{slug}/')
        raise AssertionError('A draft destination must not be available on its public route.')
    except urllib.error.HTTPError as error:
        assert error.code == 404

    editor = opener.open(BASE + f'admin/destinos.php?id={destination_id}').read().decode()
    token = re.search(r'name="csrf" value="([^"]+)"', editor).group(1)
    post('admin/destinos.php', {'csrf': token, 'action': 'delete', 'id': str(destination_id)})
    with sqlite3.connect(DB) as db:
        ids = [row[0] for row in db.execute('SELECT id FROM media_assets WHERE asset_path LIKE ?', (f'assets/destinos/{destination_id}/%',)).fetchall()]
        marks = ','.join('?' for _ in ids)
        if ids:
            db.execute(f'DELETE FROM destination_media WHERE media_asset_id IN ({marks})', ids)
            db.execute(f'DELETE FROM point_of_interest_media WHERE media_asset_id IN ({marks})', ids)
            db.execute(f'DELETE FROM media_asset_translations WHERE media_asset_id IN ({marks})', ids)
            db.execute(f'DELETE FROM media_assets WHERE id IN ({marks})', ids)
    for path in asset_paths:
        full = MEDIA_ROOT / path
        if full.is_file():
            full.unlink()
    folder = MEDIA_ROOT / 'assets' / 'destinos' / str(destination_id)
    if folder.is_dir() and not any(folder.iterdir()):
        folder.rmdir()
    print('OK: imagen de portada y atractivo cargan a disco/DB; el destino borrador se previsualiza privado y sigue oculto al público.')
finally:
    if destination_id:
        with sqlite3.connect(DB) as db:
            db.execute('DELETE FROM point_of_interest_media WHERE point_of_interest_id IN (SELECT id FROM points_of_interest WHERE destination_id=?)', (destination_id,))
            db.execute('DELETE FROM destination_media WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM point_of_interest_translations WHERE point_of_interest_id IN (SELECT id FROM points_of_interest WHERE destination_id=?)', (destination_id,))
            db.execute('DELETE FROM points_of_interest WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_translations WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_story_blocks WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_reasons WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destination_sources WHERE destination_id=?', (destination_id,))
            db.execute('DELETE FROM destinations WHERE id=?', (destination_id,))
        for path in list(MEDIA_ROOT.glob(f'assets/destinos/{destination_id}/*')) if MEDIA_ROOT.exists() else []:
            path.unlink(missing_ok=True)
