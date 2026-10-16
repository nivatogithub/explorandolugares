#!/usr/bin/env python3
"""Prepara copia identificada de cartografía DANE 2018; no afirma vigencia 2025."""
import json,hashlib,urllib.request,unicodedata,argparse
from pathlib import Path
from datetime import datetime,timezone
ROOT=Path(__file__).resolve().parents[1]
SOURCE='https://raw.githubusercontent.com/caticoa3/colombia_mapa/master/'
OUT=ROOT/'backend/public/assets/cobertura';CACHE=ROOT/'.local/coverage'
def norm(s):return ''.join(c for c in unicodedata.normalize('NFD',s.upper()) if unicodedata.category(c)!='Mn').replace('.','').strip()
def run(cached=False):
 OUT.mkdir(parents=True,exist_ok=True);CACHE.mkdir(parents=True,exist_ok=True);sources=[];datasets=[]
 for key,filename in [('departamentos','co_2018_MGN_DPTO_POLITICO.geojson'),('municipios','co_2018_MGN_MPIO_POLITICO.geojson')]:
  path=CACHE/f'{key}-2018.geojson';url=SOURCE+filename
  if not cached:path.write_bytes(urllib.request.urlopen(url,timeout=40).read())
  raw=path.read_bytes();datasets.append(json.loads(raw));sources.append({'url':url,'sha256':hashlib.sha256(raw).hexdigest()})
 departments,municipalities=datasets
 if len(departments['features'])!=33 or len(municipalities['features'])!=1122:raise ValueError('Conteo inesperado en fuente MGN 2018')
 def rounded(v):
  if isinstance(v,list):return [rounded(x) for x in v]
  return round(v,4) if isinstance(v,float) else v
 def geometry(f):return {'type':f['geometry']['type'],'coordinates':rounded(f['geometry']['coordinates'])}
 depfeatures=[];munfeatures={};catalog=[]
 for f in departments['features']:
  x=f['properties'];depfeatures.append({'type':'Feature','properties':{'code':x['DPTO_CCDGO'],'name':x['DPTO_CNMBR']},'geometry':geometry(f)})
 for f in municipalities['features']:
  x=f['properties'];row={'code':x['MPIO_CCNCT'],'department_code':x['DPTO_CCDGO'],'name':x['MPIO_CNMBR']};catalog.append(row)
  munfeatures.setdefault(x['DPTO_CCDGO'],[]).append({'type':'Feature','properties':row,'geometry':geometry(f)})
 def save(name,x):(OUT/name).write_text(json.dumps(x,ensure_ascii=False,separators=(',',':'))+'\n')
 save('departamentos.geojson',{'type':'FeatureCollection','features':depfeatures})
 for code,features in munfeatures.items():save(f'municipios-{code}.geojson',{'type':'FeatureCollection','features':features})
 private=ROOT/'backend/app/content';private.mkdir(exist_ok=True)
 (private/'coverage-geography.json').write_text(json.dumps(catalog,ensure_ascii=False,separators=(',',':'))+'\n')
 meta={'source':'Departamento Administrativo Nacional de Estadística - DANE: www.dane.gov.co','version':'MGN 2018 · copia adaptada por caticoa3/colombia_mapa','downloaded_at':datetime.now(timezone.utc).isoformat(),'license':'CC BY 4.0 (datos DANE)','license_url':'https://geoportal.dane.gov.co/acerca-del-geoportal/licencia-y-condiciones-de-uso/','modifications':'Geometrías simplificadas en la copia de origen; campos seleccionados y coordenadas redondeadas a cuatro decimales. Mapa de orientación, no delimitación legal ni censo administrativo actual.','department_count':33,'municipality_unit_count':1122,'sources':sources,'official_2025_download_status':'No disponible durante preparación: errores 500/403 del servicio; no se sortearon restricciones.'}
 save('procedencia.json',meta)
 print('Mapa preparado: 33 unidades departamentales, 1122 unidades de MGN 2018')
if __name__=='__main__':
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--cached',action='store_true');run(parser.parse_args().cached)
