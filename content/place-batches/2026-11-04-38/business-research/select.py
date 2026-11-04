#!/usr/bin/env python3
"""Select balanced review candidates, never count as approved/importable."""
import json,collections,datetime
from pathlib import Path
P=Path(__file__).resolve().parent
x=json.loads((P/'candidates.json').read_text());pool=x['businesses'];groups=collections.defaultdict(list)
for r in pool:groups[(r['destination_slug'],r['category_code'])].append(r)
def rank(r):
 t=r['source_tags'];return (bool(t.get('website') or t.get('contact:website')),bool(t.get('addr:street')),bool(t.get('phone') or t.get('contact:phone')),r.get('source_object_updated_at',''))
for g in groups.values():g.sort(key=rank,reverse=True)
chosen=[];counts=collections.Counter();category_counts=collections.Counter()
while groups and len(chosen)<1500:
 key=min(groups,key=lambda k:(counts[k[0]],category_counts[k],k))
 r=groups[key].pop(0);chosen.append(r);counts[key[0]]+=1;category_counts[key]+=1
 if not groups[key]:del groups[key]
out={'status':'balanced_selection_pending_review','target':1500,'count':len(chosen),'counts_by_municipality':dict(sorted(counts.items())),'publication_ready':False,'businesses':chosen}
(P/'selection-1500.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
# Explicit publication barriers; having a point is not verification of current operation.
review=[{'source_url':r['source_url'],'destination_slug':r['destination_slug'],'name':r['name'],'category_code':r['category_code'],'latitude':r['latitude'],'longitude':r['longitude'],'checks':{'named_cartographic_record':True,'within_municipal_boundary':True,'deduplicated':True,'independent_identity_location_contrast':False,'named_google_maps_listing':False},'status':'pending_contrast'} for r in chosen]
(P/'validation-queue.json').write_text(json.dumps(review,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'selected':len(chosen),'municipalities':len(counts),'by_municipality':dict(sorted(counts.items()))},ensure_ascii=False))
