#!/usr/bin/env python3
"""Investigate OSM businesses using municipal polygons; never publish automatically."""
import sys
sys.path.pop(0)
import collections, glob, json, math, re, subprocess, unicodedata
from pathlib import Path
import osmium
from shapely.geometry import Point, Polygon, shape
from shapely.strtree import STRtree
P=Path(__file__).resolve().parent; ROOT=P.parents[3]; REF='contenido/lote-30-reproducible-20261104'
def norm(s): return re.sub(r'[^a-z0-9]+',' ',''.join(c for c in unicodedata.normalize('NFKD',s.casefold()) if not unicodedata.combining(c))).strip()
def git_json(path): return json.loads(subprocess.check_output(['git','show',f'{REF}:{path}'],cwd=ROOT))
slugs=[r['slug'] for r in json.loads((P/'cobertura-68.json').read_text())['lugares'] if r['batch']=='30']
features=[]
for f in glob.glob(str(ROOT/'backend/public/assets/cobertura/municipios-*.geojson')): features.extend(json.loads(Path(f).read_text())['features'])
overrides={'el-penol':'PEÑOL','el-retiro':'RETIRO','la-playa-de-belen':'LA PLAYA','lorica':'LORICA','mompox':'MOMPÓS','socorro':'SOCORRO','buga':'GUADALAJARA DE BUGA'}
depts={'antioquia':'05','boyaca':'15','bolivar':'13','caldas':'17','casanare':'85','cordoba':'23','cundinamarca':'25','magdalena':'47','norte de santander':'54','santander':'68','tolima':'73','valle del cauca':'76'}
polygons=[]
for slug in slugs:
 d=git_json(f'content/place-batches/2026-11-04-30/places/{slug}/manifest.draft.json')['destination']; dep=depts[norm(d['department'])]; name=norm(overrides.get(slug,d['translations']['es']['display_name']))
 f=[f for f in features if f['properties']['department_code']==dep and norm(f['properties']['name'])==name]
 if len(f)!=1:raise RuntimeError(f'Polígono ambiguo: {slug}')
 polygons.append(shape(f[0]['geometry']))
tree=STRtree(polygons); seen=collections.defaultdict(list); rows=[]; duplicates=[]
def category(t):
 a=t.get('amenity',''); s=t.get('shop',''); tour=t.get('tourism','')
 if any(k.startswith(('disused:','abandoned:','demolished:')) for k in t) or t.get('disused')=='yes' or s in ('vacant','no'):return None
 if tour in ('hotel','hostel','guest_house','motel','apartment','chalet','camp_site'):return 'hospedaje'
 if a in ('restaurant','cafe','fast_food','food_court','ice_cream') or s in ('bakery','pastry'):return 'alimentacion'
 if a in ('bar','pub','nightclub'):return 'vida-nocturna'
 if s in ('alcohol','wine','beverages'):return 'licores'
 if a in ('pharmacy','clinic','dentist','doctors','veterinary') or s in ('chemist','optician'):return 'salud'
 if a in ('car_rental','taxi') or s in ('car_rental','motorcycle_rental','bicycle_rental'):return 'transporte'
 if s=='travel_agency':return 'experiencias'
 if a in ('cinema','theatre','arts_centre') or s in ('art','music','musical_instrument'):return 'cultura'
 if a in ('bank','bureau_de_change','post_office') or s in ('laundry','dry_cleaning','hairdresser','beauty','car_repair','copyshop') or t.get('craft'):return 'servicios'
 if s:return 'comercio'
 return None
class Handler(osmium.SimpleHandler):
 def save(self,obj,pt,typ,precision):
  tags=dict(obj.tags); name=tags.get('name','').strip(); cat=category(tags)
  if not name or not cat:return
  choices=[int(i) for i in tree.query(pt) if polygons[int(i)].covers(pt)]
  if len(choices)!=1:return
  slug=slugs[choices[0]]; key=(slug,norm(name)); near=next((x for x in seen[key] if math.hypot((x['longitude']-pt.x)*math.cos(math.radians(pt.y)),x['latitude']-pt.y)*111320<35),None)
  url=f'https://www.openstreetmap.org/{typ}/{obj.id}'
  if near:duplicates.append({'kept':near['source'],'duplicate':url});return
  row={'destination_slug':slug,'name':name,'category_code':cat,'latitude':pt.y,'longitude':pt.x,'coordinate_type':precision,'tags':tags,'source':url,'review_status':'identidad_y_vigencia_por_contrastar'}; rows.append(row);seen[key].append(row)
 def node(self,obj):
  if 'name' not in obj.tags or not obj.location.valid():return
  self.save(obj,Point(obj.location.lon,obj.location.lat),'node','mapped_point')
 def way(self,obj):
  if 'name' not in obj.tags or category(dict(obj.tags)) is None or not obj.is_closed():return
  try:
   points=[(n.location.lon,n.location.lat) for n in obj.nodes if n.location.valid()]
   if len(points)<4:return
   poly=Polygon(points)
   if not poly.is_valid or poly.is_empty:return
   self.save(obj,poly.representative_point(),'way','point_inside_mapped_footprint_not_entrance')
  except (ValueError,RuntimeError):return
Handler().apply_file(sys.argv[1],locations=True)
out={'checked_at':'2026-11-04','status':'research_not_publication','source_extract':'https://download.geofabrik.de/south-america/colombia-261103.osm.pbf','boundary_source':'backend/public/assets/cobertura/municipios-*.geojson; simplified polygons','attribution':'© OpenStreetMap contributors','license':'ODbL 1.0','note':'Posición en polígono municipal aproximado; vigencia, identidad y límites a contrastar.','counts':dict(collections.Counter(x['destination_slug'] for x in rows)),'businesses':rows,'potential_duplicates':duplicates}
(P/'osm-lote30-extraido.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'total':len(rows),'counts':out['counts'],'duplicates':len(duplicates)},ensure_ascii=False))
