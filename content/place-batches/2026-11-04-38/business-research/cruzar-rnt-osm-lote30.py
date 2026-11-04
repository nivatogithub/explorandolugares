#!/usr/bin/env python3
"""Create conservative exact-name RNT/OSM matches for dev1 review."""
import collections, json, re, unicodedata
from pathlib import Path
P=Path(__file__).resolve().parent
rnt=json.loads((P/'rnt-lote30-2026.json').read_text())['businesses']
osm=json.loads((P/'lote30-candidatos-priorizados.json').read_text())['candidatos']
def norm(s):
    return re.sub(r'[^a-z0-9]+',' ',''.join(c for c in unicodedata.normalize('NFKD',s.casefold()) if not unicodedata.combining(c))).strip()
index=collections.defaultdict(list)
for r in rnt:
    index[(r['destination_slug'],norm(r.get('razon_social_establecimiento','')))].append(r)
excluded_names={'el mirador de chalo','mapagua','hotel piedra de moler','casa de huespedes donde lorenzo'}
rows=[]
for x in osm:
    matches=index[(x['destination_slug'],norm(x['name']))]
    if len(matches)!=1 or norm(x['name']) in excluded_names: continue
    r=matches[0]; cat=norm(r['categoria']); typ=x['category_code']
    if typ=='hospedaje' and not ('alojamiento' in cat or 'viviendas turisticas' in cat or 'hospedaje' in cat): continue
    if typ=='alimentacion' and 'gastronomia' not in cat: continue
    if typ not in ('hospedaje','alimentacion'): continue
    rows.append({'destination_slug':x['destination_slug'],'name':x['name'],'category_code':typ,'latitude':x['latitude'],'longitude':x['longitude'],'source_map':x['source'],'source_identity':'https://www.datos.gov.co/d/thwd-ivmp','rnt':r['codigo_rnt'],'rnt_address':r.get('direcci_n_comercial_establecimiento',''),'evidence':f'Nombre exacto, categoría y municipio coinciden con RNT {r["codigo_rnt"]} activo en fuente 2026; punto OSM orientativo sin confirmar correspondencia exacta ni funcionamiento actual.'})
out={'status':'matched_name_and_municipality_location_unconfirmed','rnt_source':'https://www.datos.gov.co/d/thwd-ivmp','osm_attribution':'© OpenStreetMap contributors, ODbL 1.0','rows':rows}
(P/'lote30-cruce-rnt-osm.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'count':len(rows),'by_place':dict(collections.Counter(x['destination_slug'] for x in rows))},ensure_ascii=False))
