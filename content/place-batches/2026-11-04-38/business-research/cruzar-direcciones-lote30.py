#!/usr/bin/env python3
"""Queue RNT/OSM address/name candidates for manual verification."""
import collections, difflib, json, re, unicodedata
from pathlib import Path
P=Path(__file__).resolve().parent
osm=json.loads((P/'osm-lote30-extraido.json').read_text())['businesses']
rnt=json.loads((P/'rnt-lote30-2026.json').read_text())['businesses']
def norm(s):
 return re.sub(r'[^a-z0-9]+',' ',''.join(c for c in unicodedata.normalize('NFKD',s.casefold()) if not unicodedata.combining(c))).strip()
def tokens(s):
 return [x for x in norm(s).split() if x not in {'hotel','hostal','restaurante','hospedaje','cafe','boutique','sas','de','la','del','el','y','las','los'}]
group=collections.defaultdict(list)
for r in rnt:group[r['destination_slug']].append(r)
rows=[]
for x in osm:
 t=x['tags']; street=t.get('addr:street',''); house=t.get('addr:housenumber','')
 if not street or not house:continue
 streetnums=re.findall(r'\d+[a-z]?',norm(street)); housenums=re.findall(r'\d+[a-z]?',norm(house))
 if not streetnums or len(housenums)<2:continue
 candidates=[]
 for r in group[x['destination_slug']]:
  category=norm(r.get('categoria','')); typ=x['category_code']
  if typ=='hospedaje' and not any(v in category for v in ('alojamiento','hospedaje','viviendas turisticas')):continue
  if typ=='alimentacion' and 'gastronomia' not in category:continue
  if typ not in ('hospedaje','alimentacion'):continue
  address=norm(r.get('direcci_n_comercial_establecimiento','')); nums=re.findall(r'\d+[a-z]?',address)
  if not all(n in nums for n in streetnums+housenums):continue
  name=' '.join(tokens(x['name'])); target=' '.join(tokens(r.get('razon_social_establecimiento','')))
  if len(name)<5 or len(target)<5:continue
  score=difflib.SequenceMatcher(None,name,target).ratio()
  if score>=0.68:candidates.append((score,r))
 if len(candidates)==1:
  score,r=candidates[0]; rows.append({'destination_slug':x['destination_slug'],'name':x['name'],'category_code':x['category_code'],'latitude':x['latitude'],'longitude':x['longitude'],'source_map':x['source'],'source_identity':'https://www.datos.gov.co/d/thwd-ivmp','rnt':r['codigo_rnt'],'rnt_name':r['razon_social_establecimiento'],'rnt_address':r.get('direcci_n_comercial_establecimiento',''),'osm_address':street+' '+house,'name_score':round(score,3),'status':'pendiente_de_revision_no_publicar'})
out={'status':'pendiente_de_revision_no_publicar','method':'única coincidencia por municipio, números de dirección, categoría y similitud de nombre; requiere comprobación','count':len(rows),'rows':rows}
(P/'lote30-cruce-direcciones-pendiente.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'count':len(rows),'municipios':dict(collections.Counter(x['destination_slug'] for x in rows))},ensure_ascii=False))
