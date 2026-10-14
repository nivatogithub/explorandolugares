#!/usr/bin/env python3
"""Import one destination package into an isolated local preview server.

Example:
  python3 scripts/ensayar-paquete-destino.py \
    --package .local/paquetes/sonson/paquete-destino-sonson-v1.json \
    --images .local/paquetes/sonson/images \
    --base http://127.0.0.1:8015/v1/ \
    --db .local/sonson-preview.sqlite \
    --media-root .local/sonson-media --keep
"""
import argparse
import http.cookiejar
import json
import re
import sqlite3
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path


def multipart(fields, files):
    boundary = '----el-package-' + uuid.uuid4().hex
    body = bytearray()
    for name, value in fields.items():
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    for name, path in files:
        data = path.read_bytes()
        content_type = {'jpg':'image/jpeg','png':'image/png','webp':'image/webp'}[path.suffix.lower().lstrip('.')]
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{path.name}"\r\nContent-Type: {content_type}\r\n\r\n'.encode())
        body.extend(data + b'\r\n')
    body.extend(f'--{boundary}--\r\n'.encode())
    return bytes(body), boundary


def image_fields(prefix, metadata):
    result = {}
    for key in ('image_origin','image_creator','image_source_url','image_license','image_license_url'):
        value = metadata.get(key, '')
        if key == 'image_origin' and not value:
            value = 'ai_generated'
        result[f'{prefix}[{key}]' if prefix else key] = str(value)
    for locale in ('es','en','pt-BR'):
        for key in ('alt','caption'):
            result[f'{prefix}[image_{key}][{locale}]' if prefix else f'image_{key}[{locale}]'] = (metadata.get(key) or {}).get(locale, '')
    return result


def run():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--package', required=True, type=Path)
    parser.add_argument('--images', required=True, type=Path)
    parser.add_argument('--base', required=True, help='Local preview root, e.g. http://127.0.0.1:8015/v1/')
    parser.add_argument('--db', required=True, type=Path)
    parser.add_argument('--media-root', required=True, type=Path)
    parser.add_argument('--keep', action='store_true', help='Keep the destination draft and uploaded images after checks.')
    args = parser.parse_args()
    base = args.base.rstrip('/') + '/'
    pack = json.loads(args.package.read_text(encoding='utf-8'))
    if pack.get('schema_version') != 1 or not isinstance(pack.get('destination'), dict) or not isinstance(pack.get('places'), list):
        raise SystemExit('El archivo no cumple el paquete de destinos schema_version 1.')
    destination = pack['destination']
    slug = destination['slug']
    if not args.keep:
        with sqlite3.connect(args.db) as db:
            if db.execute('SELECT 1 FROM destinations WHERE slug=?', (slug,)).fetchone():
                raise SystemExit(f'Ya existe el destino {slug}; usa otra base local o retíralo primero.')

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    login = opener.open(base + 'admin/index.php').read().decode()
    csrf = re.search(r'name="csrf" value="([^"]+)"', login).group(1)
    opener.open(urllib.request.Request(base + 'admin/index.php', data=urllib.parse.urlencode({
        'csrf':csrf,'action':'login','username':'localadmin','password':'DestinosLocal8014!'
    }).encode()))
    editor = opener.open(base + 'admin/destinos.php').read().decode()
    csrf = re.search(r'name="csrf" value="([^"]+)"', editor).group(1)

    fields = {'csrf':csrf,'action':'save','id':'','slug':slug,'destination_type':'municipality','country_code':'CO',
              'department':destination.get('department',''),'subregion':destination.get('subregion',''),
              'latitude':str(destination.get('latitude') or ''),'longitude':str(destination.get('longitude') or ''),
              'publication_status':'draft','display_order':'999',
              'internal_research_json':json.dumps(pack.get('local_research',{'schema_version':1}),ensure_ascii=False)}
    for locale in ('es','en','pt-BR'):
        translation=(destination.get('translations') or {}).get(locale,{})
        for key in ('display_name','eyebrow','headline','intro','meta_description'):
            fields[f'translations[{locale}][{key}]']=translation.get(key,'')
        fields[f'history[{locale}]']='\n'.join((destination.get('history') or {}).get(locale,[]))
        fields[f'reasons[{locale}]']='\n'.join((destination.get('reasons') or {}).get(locale,[]))
        sources=(destination.get('sources') or {}).get(locale,[])
        fields[f'sources[{locale}]']='\n'.join(f"{s.get('section','destination')}|{s.get('label','')}|{s.get('url','')}" for s in sources)
    hero=pack.get('hero_image',{})
    fields.update(image_fields('',hero))
    files=[]
    hero_path=args.images / f'{slug}-portada.webp'
    if hero_path.is_file():
        files.append(('hero_upload',hero_path))
    for index, place in enumerate(pack['places']):
        fields[f'places[{index}][slug]']=place.get('slug','')
        fields[f'places[{index}][publication_status]']='draft'
        fields[f'places[{index}][media_asset_id]']='0'
        for locale in ('es','en','pt-BR'):
            text=(place.get('translations') or {}).get(locale,{})
            fields[f'places[{index}][translations][{locale}][name]']=text.get('name','')
            fields[f'places[{index}][translations][{locale}][description]']=text.get('description','')
        image=place.get('image',{})
        fields.update(image_fields(f'places[{index}]',image))
        image_path=args.images / f'{place.get("slug", "")}.webp'
        if image_path.is_file():
            files.append((f'place_uploads[{index}]',image_path))

    body,boundary=multipart(fields,files)
    response=opener.open(urllib.request.Request(base+'admin/destinos.php',data=body,headers={'Content-Type':f'multipart/form-data; boundary={boundary}'}))
    saved_id=int(re.search(r'[?&]id=(\d+)',response.geturl()).group(1))
    with sqlite3.connect(args.db) as db:
        row=db.execute('SELECT slug,publication_status,internal_research_json FROM destinations WHERE id=?',(saved_id,)).fetchone()
        assert row and row[:2]==(slug,'draft'),'El destino no quedó en borrador.'
        research=json.loads(row[2])
        assert research.get('competitors') and research.get('business_prospects') and research.get('acquisition_strategies'),'No se guardó la investigación comercial completa.'
        assets=db.execute('SELECT asset_path,origin FROM media_assets WHERE asset_path LIKE ?',(f'assets/destinos/{saved_id}/%',)).fetchall()
        assert len(assets)==len(files),'No se registraron todas las imágenes del paquete.'
        assert all(origin=='ai_generated' for _,origin in assets),'El origen de las ilustraciones debe conservarse.'

    preview=opener.open(base+f'destinos/{slug}/?preview=1').read().decode()
    assert 'Vista previa privada' in preview,'El borrador no abre en vista previa privada.'
    assert destination['translations']['es']['display_name'] in preview,'La vista previa no muestra el destino importado.'
    assert not any(item['name'] in preview for item in research.get('business_prospects',[])),'Los prospectos internos se filtraron a la vista pública.'
    for relative,_ in assets:
        image=opener.open(base+'assets/destinos/'+str(saved_id)+'/'+Path(relative).name).read()
        assert image,'No se pudo servir una imagen cargada.'
    try:
        opener.open(base+f'destinos/{slug}/')
        raise AssertionError('El destino en borrador quedó visible sin autenticación.')
    except urllib.error.HTTPError as error:
        assert error.code==404
    print(f'OK: {slug} (ID {saved_id}) importado en borrador; {len(pack["places"])} atractivos, {len(assets)} imágenes, investigación privada, preview autenticado y ruta pública oculta.')
    if args.keep:
        print(f'Conservado para revisión: {base}destinos/{slug}/?preview=1')
        return

    with sqlite3.connect(args.db) as db:
        db.execute('DELETE FROM point_of_interest_media WHERE point_of_interest_id IN (SELECT id FROM points_of_interest WHERE destination_id=?)',(saved_id,))
        db.execute('DELETE FROM destination_media WHERE destination_id=?',(saved_id,))
        db.execute('DELETE FROM point_of_interest_translations WHERE point_of_interest_id IN (SELECT id FROM points_of_interest WHERE destination_id=?)',(saved_id,))
        db.execute('DELETE FROM points_of_interest WHERE destination_id=?',(saved_id,))
        db.execute('DELETE FROM destination_translations WHERE destination_id=?',(saved_id,))
        db.execute('DELETE FROM destination_story_blocks WHERE destination_id=?',(saved_id,))
        db.execute('DELETE FROM destination_reasons WHERE destination_id=?',(saved_id,))
        db.execute('DELETE FROM destination_sources WHERE destination_id=?',(saved_id,))
        db.execute('DELETE FROM destinations WHERE id=?',(saved_id,))
        db.commit()
    for relative,_ in assets:
        (args.media_root/relative).unlink(missing_ok=True)


if __name__=='__main__':
    run()
