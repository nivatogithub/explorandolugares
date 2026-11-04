#!/usr/bin/env python3
import sys,json,urllib.request,xml.etree.ElementTree as ET
from pathlib import Path
root=Path(__file__).resolve().parents[1];slug,lat,lon=sys.argv[1:4];lat,lon=float(lat),float(lon);d=.02
url=f'https://api.openstreetmap.org/api/0.6/map?bbox={lon-d},{lat-d},{lon+d},{lat+d}'
with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugaresEditorialResearch/1.0'}),timeout=60) as r:raw=r.read()
xml=ET.fromstring(raw);nodes={n.attrib['id']:(float(n.attrib['lat']),float(n.attrib['lon'])) for n in xml.findall('node')};items=[]
for e in xml:
 tags={t.attrib['k']:t.attrib['v'] for t in e.findall('tag')}
 if not tags.get('name'):continue
 if not any(k in tags for k in ['natural','man_made','aerialway','highway','tourism']):continue
 if e.tag=='node':point=nodes[e.attrib['id']]
 elif e.tag=='way':
  pts=[nodes[x.attrib['ref']] for x in e.findall('nd') if x.attrib['ref'] in nodes]
  if not pts:continue
  point=(sum(x[0] for x in pts)/len(pts),sum(x[1] for x in pts)/len(pts))
 else:continue
 items.append({'name':tags['name'],'latitude':point[0],'longitude':point[1],'point_kind':'mapped_feature_reference_not_verified_entrance','source_url':f'https://www.openstreetmap.org/{e.tag}/{e.attrib["id"]}','tags':tags})
p=root/'places'/slug/'mapped-landmarks.json';p.write_text(json.dumps({'source_url':url,'license':'ODbL 1.0','checked_at':'2026-11-04','items':items},ensure_ascii=False,indent=2));print(p)
