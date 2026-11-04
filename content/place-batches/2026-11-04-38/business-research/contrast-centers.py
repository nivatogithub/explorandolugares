#!/usr/bin/env python3
import json,urllib.request,urllib.parse,math
from pathlib import Path
P=Path(__file__).resolve().parent;B=P.parent
bound=json.loads((P/'boundaries.geojson').read_text());centers=json.loads((B/'destination-centers-candidates.json').read_text());qids={f['properties']['destination_slug']:f['properties']['tags'].get('wikidata') for f in bound['features']};ids=[x for x in qids.values() if x];params={'action':'wbgetentities','ids':'|'.join(ids),'props':'claims|labels','languages':'es','format':'json'};url='https://www.wikidata.org/w/api.php?'+urllib.parse.urlencode(params)
req=urllib.request.Request(url,headers={'User-Agent':'ExplorandoLugares editorial research/1.0 (https://explorandolugares.com)'})
with urllib.request.urlopen(req,timeout=80) as r:entities=json.load(r)['entities']
out=[]
for slug,points in centers.items():
 point=points[0];qid=qids.get(slug);entity=entities.get(qid,{});claim=entity.get('claims',{}).get('P625',[]);coords=[]
 for item in claim:
  try:
   v=item['mainsnak']['datavalue']['value'];lat=v['latitude'];lon=v['longitude'];dlon=math.radians(lon-point['longitude']);dlat=math.radians(lat-point['latitude']);a=math.sin(dlat/2)**2+math.cos(math.radians(lat))*math.cos(math.radians(point['latitude']))*math.sin(dlon/2)**2;distance=round(12742000*math.asin(min(1,math.sqrt(a))));coords.append({'latitude':lat,'longitude':lon,'distance_m':distance,'precision':v.get('precision')})
  except (KeyError,TypeError):pass
 out.append({'slug':slug,'candidate':point,'wikidata_id':qid,'wikidata_url':'https://www.wikidata.org/wiki/'+qid if qid else None,'wikidata_label':entity.get('labels',{}).get('es',{}).get('value'),'wikidata_coordinates':coords,'status':'corroborado_centro_urbano' if coords and min(c['distance_m'] for c in coords)<=1200 else 'pendiente_revision_distancia_o_fuente','note':'Coordenada de localidad, no necesariamente parque o entrada; Wikidata puede compartir datos con OSM.'})
(B/'destination-centers-contrast.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print(json.dumps({'total':len(out),'corroborated':sum(x['status']=='corroborado_centro_urbano' for x in out),'pending':[(x['slug'],x['wikidata_coordinates']) for x in out if x['status']!='corroborado_centro_urbano']},ensure_ascii=False))
