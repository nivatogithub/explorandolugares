#!/usr/bin/env python3
import json,re,unicodedata,collections,math,difflib
from pathlib import Path
P=Path(__file__).resolve().parent
p=P/'candidates.json';d=json.loads(p.read_text())
def norm(s):return re.sub(r'[^a-z0-9]+',' ',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower()).strip()
generic=set('tienda|drogueria|panaderia|restaurante|restaurantes|comidas rapidas|montallantas|casa|hospedaje|hotel|hostal|hostal y restaurante|consultorio odontologico|consultorio medico|peluqueria|barberia|supermercado|minimercado|ferreteria|parqueadero|cafeteria|cafe|bar|almacen|local|sin nombre|no name|minimarket|carniceria|miscelanea|miscelanea papeleria|papeleria|salon de belleza|taller|taller de motos|taller de bicicletas|taller de mecanica|veterinaria|heladeria|lavadero|parqueadero motos|boutique|tienda de ropa|variedades|finca|chalet|camping|farmacia|bicicleteria|zapateria|cerrajeria|carpinteria|lavanderia|restaurante y cafeteria'.split('|'))
excluded=d.get('excluded_candidates',[]);out=[]
for r in d['businesses']:
 n=norm(r['name']);t=r['source_tags'];reason=None
 if n in generic or len(n)<2 or n.isdigit():reason='Nombre genérico: no identifica inequívocamente un negocio.'
 elif re.search(r'justo (?:y )?bueno',n):reason='Marca con proceso de liquidación documentado; vigencia de esta ficha no confirmada.'
 elif t.get('access')=='private':reason='Acceso privado; no se confirma establecimiento abierto al público.'
 elif t.get('tourism')=='camp_site' and not (t.get('website') or t.get('contact:website') or t.get('operator')):reason='Punto de campamento sin evidencia de operación comercial.'
 if reason:excluded.append(dict(r,exclusion_reason=reason))
 else:out.append(r)
# Similar names at the same location are held for review, never silently merged.
bycell=collections.defaultdict(list); kept=[]
for r in out:
 key=(r['destination_slug'],r['category_code']); n=norm(r['name']); duplicate=None
 for prev in bycell[key]:
  n2=norm(prev['name']); dist=math.hypot((prev['longitude']-r['longitude'])*math.cos(math.radians(r['latitude'])),prev['latitude']-r['latitude'])*111320
  if dist<20 and (difflib.SequenceMatcher(None,n,n2).ratio()>=.82 or (min(len(n),len(n2))>=12 and (n in n2 or n2 in n))): duplicate=prev;break
 if duplicate:excluded.append(dict(r,exclusion_reason='Posible duplicado por nombre parecido y ubicación cercana; requiere revisión.',possible_duplicate_of=duplicate['source_url']))
 else:kept.append(r);bycell[key].append(r)
out=kept
d['businesses']=out;d['candidate_count']=len(out);d['excluded_candidates']=excluded;d['counts_by_place_category']={s:dict(collections.Counter(r['category_code'] for r in out if r['destination_slug']==s)) for s in d['counts_by_place_category']}
d['exclusion_sources']=['https://www.supersociedades.gov.co/es/noticias-supersociedades/-/asset_publisher/atwl/content/proceso-mercader%C3%ADa-s.a.s.-justo-y-bueno-1']
p.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
c=json.loads((P/'coverage.json').read_text());c['located_candidates']=len(out);c['excluded']=len(excluded)
for m in c['municipalities']:m['categories']=d['counts_by_place_category'][m['destination_slug']];m['located_candidates']=sum(m['categories'].values())
(P/'coverage.json').write_text(json.dumps(c,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'candidates':len(out),'excluded':len(excluded),'municipalities_with_candidates':sum(bool(x) for x in d['counts_by_place_category'].values())}))
