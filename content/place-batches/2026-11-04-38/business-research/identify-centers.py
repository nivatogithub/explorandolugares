import json,unicodedata,re,osmium
from pathlib import Path
from shapely.geometry import shape,Point
b=Path('content/place-batches/2026-11-04-38'); p=b/'business-research'; bounds={f['properties']['destination_slug']:shape(f['geometry']) for f in json.loads((p/'boundaries.geojson').read_text())['features']};ms=[json.loads(x.read_text()) for x in (b/'places').glob('*/manifest.json')]
def norm(s):return re.sub(r'[^a-z0-9]+',' ',unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode().lower()).strip()
names={m['destination']['slug']:norm(m['destination']['translations']['es']['display_name'].split(',')[0]) for m in ms};names['cucuta']='cucuta'; names['pueblo-viejo']='puebloviejo';names['santa-rosa-de-lima']='santa rosa';out={s:[] for s in names}
for o in osmium.FileProcessor('/tmp/el-colombia-261103.osm.pbf',osmium.osm.NODE).with_filter(osmium.filter.KeyFilter('place')):
 t=dict(o.tags)
 if t.get('place') not in ('city','town','village') or not t.get('name'):continue
 for slug,name in names.items():
  if norm(t['name'])==name and bounds[slug].covers(Point(o.lon,o.lat)):out[slug].append({'name':t['name'],'latitude':o.lat,'longitude':o.lon,'source':f'https://www.openstreetmap.org/node/{o.id}','type':t['place']})
f=b/'destination-centers-candidates.json';f.write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'unique':sum(len(v)==1 for v in out.values()),'pending':{k:v for k,v in out.items() if len(v)!=1}},ensure_ascii=False))
