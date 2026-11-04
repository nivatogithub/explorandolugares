#!/usr/bin/env python3
"""Inventory named OSM candidates for the 30-place batch without publishing."""
import sys
sys.path.pop(0)
import collections
import json
import subprocess
import unicodedata
from pathlib import Path

P = Path(__file__).resolve().parent
ROOT = P.parents[3]
REF = 'contenido/lote-30-reproducible-20261104'
def read(path):
    return json.loads(subprocess.check_output(['git','show',f'{REF}:{path}'],cwd=ROOT))
source = read('content/place-batches/2026-11-04-30/businesses/expanded-candidates.json')
audit = read('content/place-batches/2026-11-04-30/evidence/directory-audit.json')
current = json.loads((P/'cobertura-68.json').read_text())
visible = {x['slug']:x['total'] for x in current['lugares'] if x['batch']=='30'}
allowed = {x['slug'] for x in audit['places']}
def norm(s):
    return ''.join(c for c in unicodedata.normalize('NFKD',s.casefold()) if not unicodedata.combining(c)).strip()
groups = collections.defaultdict(list)
excluded = []
seen = set()
for r in source['businesses']:
    slug=r.get('destination_slug'); name=r.get('name','').strip(); lat=r.get('latitude'); lon=r.get('longitude'); cat=r.get('category_code')
    key=(slug,norm(name),cat)
    reason=None
    if slug not in allowed: reason='municipio no incluido en lote'
    elif not name or not cat or not isinstance(lat,(int,float)) or not isinstance(lon,(int,float)): reason='identidad, categoría o coordenadas incompletas'
    elif not (-5 <= lat <= 14 and -82 <= lon <= -66): reason='coordenadas fuera del rango nacional aproximado'
    elif key in seen: reason='nombre y categoría duplicados en municipio; revisar sedes'
    if reason: excluded.append({'registro':r,'motivo':reason}); continue
    seen.add(key); groups[(slug,cat)].append(r)
for rows in groups.values():
    rows.sort(key=lambda r:(bool(r.get('tags',{}).get('website')),bool(r.get('tags',{}).get('addr:street')),bool(r.get('tags',{}).get('phone'))),reverse=True)
ordered=[]; selected=collections.Counter(); cats=collections.Counter()
while groups:
    key=min(groups,key=lambda k:((visible.get(k[0]) if visible.get(k[0]) is not None else 9999)+selected[k[0]],cats[k],k))
    r=groups[key].pop(0); ordered.append(r); selected[key[0]]+=1; cats[key]+=1
    if not groups[key]: del groups[key]
out={'fecha':'2026-11-04','estado':'priorizados_para_validacion_no_publicados','fuente':REF+':content/place-batches/2026-11-04-30/businesses/expanded-candidates.json','licencia':source['license'],'atribucion':source['attribution'],'advertencia':'Ubicaciones OSM, no confirma funcionamiento ni correspondencia exacta; no se han contrastado independientemente ni incorporado a dev1.','counts_by_place':dict(sorted(selected.items())),'candidatos':ordered,'excluidos':excluded,'excepciones_originales':source['exceptions']}
(P/'lote30-candidatos-priorizados.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'candidatos':len(ordered),'excluidos':len(excluded),'municipios':len(selected),'sin_candidatos':sorted(allowed-set(selected)),'por_municipio':dict(sorted(selected.items()))},ensure_ascii=False))
