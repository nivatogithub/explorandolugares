#!/usr/bin/env python3
"""Retrieve official RNT records for the 30 destinations; research only."""
import sys
sys.path.pop(0)
import collections, glob, json, subprocess, unicodedata, urllib.parse, urllib.request
from pathlib import Path
P=Path(__file__).resolve().parent
ROOT=P.parents[3]
REF='contenido/lote-30-reproducible-20261104'
def git_json(path):
    return json.loads(subprocess.check_output(['git','show',f'{REF}:{path}'],cwd=ROOT))
def norm(s):
    return ''.join(c for c in unicodedata.normalize('NFKD',s.casefold()) if not unicodedata.combining(c)).replace('-',' ').strip()
coverage=json.loads((P/'cobertura-68.json').read_text())
slugs=[r['slug'] for r in coverage['lugares'] if r['batch']=='30']
features=[]
for f in glob.glob(str(ROOT/'backend/public/assets/cobertura/municipios-*.geojson')):
    features.extend(json.loads(Path(f).read_text())['features'])
overrides={'el-penol':'PEÑOL','el-retiro':'RETIRO','la-playa-de-belen':'LA PLAYA','lorica':'LORICA','mompox':'MOMPÓS','socorro':'SOCORRO','buga':'GUADALAJARA DE BUGA'}
dept_codes={'antioquia':'05','boyaca':'15','bolivar':'13','caldas':'17','casanare':'85','cordoba':'23','cundinamarca':'25','magdalena':'47','norte de santander':'54','santander':'68','tolima':'73','valle del cauca':'76'}
codes={}
for slug in slugs:
    d=git_json(f'content/place-batches/2026-11-04-30/places/{slug}/manifest.draft.json')['destination']
    dep=dept_codes[norm(d['department'])]
    name=overrides.get(slug,d['translations']['es']['display_name'])
    matches=[f['properties'] for f in features if f['properties']['department_code']==dep and norm(f['properties']['name'])==norm(name)]
    if len(matches)!=1: raise RuntimeError(f'Código ambiguo: {slug} {dep} {name} {matches}')
    code=matches[0]['code']; codes[code]=slug; codes[str(int(code))]=slug
params={'$select':'codigo_rnt,cod_mun,municipio,departamento,estado_rnt,razon_social_establecimiento,categoria,sub_categoria,direcci_n_comercial_establecimiento,ano,periodo','$where':"cod_mun in ("+','.join("'"+c+"'" for c in codes)+") AND estado_rnt='ACTIVO' AND ano='2026'",'$limit':'50000'}
url='https://www.datos.gov.co/resource/thwd-ivmp.json?'+urllib.parse.urlencode(params)
req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugares research/1.0','Accept':'application/json'})
with urllib.request.urlopen(req,timeout=120) as r: raw=json.load(r)
seen=set(); businesses=[]
for row in raw:
    key=(row.get('codigo_rnt'),row.get('cod_mun'))
    if not key[0] or key in seen: continue
    seen.add(key); row['destination_slug']=codes[row['cod_mun']]; row['status']='activo_en_datos_2026_ubicacion_y_vigencia_por_contrastar'; businesses.append(row)
result={'source':'https://www.datos.gov.co/d/thwd-ivmp','checked_at':'2026-11-04','scope':'30 lugares','period_filter':'2026','note':'Estado activo publicado en el registro; no confirma operación actual, ubicación ni condición de aliado.','total':len(businesses),'counts':dict(sorted(collections.Counter(x['destination_slug'] for x in businesses).items())),'municipalities_without_results':sorted(set(slugs)-{x['destination_slug'] for x in businesses}),'businesses':businesses}
(P/'rnt-lote30-2026.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({k:v for k,v in result.items() if k!='businesses'},ensure_ascii=False))
