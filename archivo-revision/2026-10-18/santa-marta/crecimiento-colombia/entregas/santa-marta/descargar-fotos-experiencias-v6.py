from pathlib import Path
import json,subprocess,urllib.parse,hashlib
r=Path(__file__).resolve().parent;o=r/'imagenes/experiencias-v6/originales';o.mkdir(parents=True,exist_ok=True)
rows=[
 dict(id='SM-E6-01',experience='buceo-taganga',title='Tayrona Diving 3.jpg',identity='Isla Aguja, Tayrona, salida desde Taganga; comprobar si aparecen buzos o solo paisaje submarino.'),
 dict(id='SM-E6-02',experience='caminata-tayrona',title='099 Tayrona Park Hike Begins.JPG',identity='Comprobar sendero real de Tayrona y utilidad como escena de caminata.'),
 dict(id='SM-E6-03',experience='minca-cafe',source='https://www.pexels.com/photo/vibrant-green-hummingbird-in-minca-colombia-30844600/',original_url='https://images.pexels.com/photos/30844600/pexels-photo-30844600.jpeg',author='Diana Paola Echeverria',license='Pexels License',license_url='https://www.pexels.com/license/',identity='Colibrí en Minca según ficha; solo contexto de naturaleza, no prueba de finca cafetera ni ave garantizada en tour.'),
 dict(id='SM-E6-04',experience='recorrido-centro',title='Parque de los Novios Santa Marta.jpg',identity='Parque de los Novios Santa Marta, parada del recorrido; no foto de Visit Santa Marta ni de su guía.'),
 dict(id='SM-E6-05',experience='ciudad-perdida',title='Teyuna - Lost City Aerial Image.jpg',identity='Parque arqueológico Teyuna, Sierra Nevada; no foto de Magic Tour ni prueba de acceso actual.'),
 dict(id='SM-E6-06',experience='velero-caribe',source='https://www.pexels.com/photo/serene-sunset-sailing-in-santa-marta-colombia-35894313/',original_url='https://images.pexels.com/photos/35894313/pexels-photo-35894313.jpeg',author='Keyla Brito',license='Pexels License',license_url='https://www.pexels.com/license/',identity='Velero y atardecer Santa Marta según ficha; no embarcación identificada del operador.')]
for row in rows:
 row.update(approval=False,generation_authorized=False,consulted_at='2026-10-17',status='pending_download')
 if 'title'in row:
  row['source']='https://commons.wikimedia.org/wiki/File:'+urllib.parse.quote(row['title'].replace(' ','_'));meta=o/(row['id']+'-commons.json')
  if not meta.exists():
   q='https://commons.wikimedia.org/w/api.php?'+urllib.parse.urlencode(dict(action='query',format='json',prop='imageinfo',iiprop='url|size|extmetadata',iiurlwidth=1280,titles='File:'+row['title']))
   result=subprocess.run(['curl','--fail','--location','--max-time','25','--output',str(meta),q],capture_output=True)
   if result.returncode:row['status']='metadata_failed';continue
  try:
   info=next(iter(json.loads(meta.read_text())['query']['pages'].values()))['imageinfo'][0];e=info['extmetadata'];row.update(author=e.get('Artist',{}).get('value',''),license=e.get('LicenseShortName',{}).get('value',''),license_url=e.get('LicenseUrl',{}).get('value',''),original_url=info['url'],source_dimensions=[info['width'],info['height']],date=e.get('DateTimeOriginal',{}).get('value'))
   if row['license']not in ['CC BY-SA 4.0','CC BY-SA 3.0','CC BY-SA 2.0','CC BY 2.0','CC BY 3.0','CC BY 4.0','CC0']:
    row['status']='license_pending_review';continue
  except (ValueError,KeyError,StopIteration):row['status']='metadata_invalid';continue
 f=o/(row['id']+'.jpg');downloadurl=row['original_url'];variant='original'
 if not f.exists():
  result=subprocess.run(['curl','--fail','--location','--max-time','30','--output',str(f)+'.part',downloadurl],capture_output=True)
  if result.returncode and 'title'in row and info.get('thumburl'):
   downloadurl=info['thumburl'];variant='official_1280px_variant';result=subprocess.run(['curl','--fail','--location','--max-time','20','--output',str(f)+'.part',downloadurl],capture_output=True)
  if result.returncode:row['status']='download_failed';continue
  Path(str(f)+'.part').replace(f);f.with_suffix('.download.json').write_text(json.dumps(dict(download_url=downloadurl,variant=variant)))
 row.update(json.loads(f.with_suffix('.download.json').read_text()));row.update(status='downloaded_pending_inspection',path=str(f.relative_to(r)),sha256=hashlib.sha256(f.read_bytes()).hexdigest(),bytes=f.stat().st_size)
 print(row['id'],row['status'],flush=True)
(r/'fotos-experiencias-v6.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
print([(x['id'],x['status']) for x in rows],flush=True)
