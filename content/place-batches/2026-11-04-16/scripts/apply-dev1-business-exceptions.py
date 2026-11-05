#!/usr/bin/env python3
"""Apply only owner-approved named exceptions to the existing dev1 policy, preserving all others."""
import argparse,hashlib,json
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('--state',type=Path,required=True);p.add_argument('--receipt',type=Path,required=True);a=p.parse_args()
state=a.state.resolve();assert str(state).endswith('/.local/environments/dev1'), 'Only dev1 supported'
root=Path(__file__).resolve().parents[1];approved=json.loads((root/'business-location-exceptions.json').read_text());names={(x['place'],x['name']) for x in approved['records']}
slug_names={json.loads(p.read_text())['slug']:json.loads(p.read_text())['name'] for p in (root/'places').glob('*/research.json')}
r=json.loads(a.receipt.read_text());assert r['environment']=='dev1';rows=r['exceptions'];assert len(rows)==47 and len({x['public_id'] for x in rows})==47
assert {(slug_names[x['destination_slug']],x['name']) for x in rows}==names
policy=state/'release/app/content/geolocation-policy.json';before=policy.read_bytes();d=json.loads(before);old=list(d['business_exceptions']);added=[x['public_id'] for x in rows if x['public_id'] not in old];d['business_exceptions']=old+added
out=json.dumps(d,ensure_ascii=False,indent=2)+'\n';tmp=policy.with_suffix('.json.batch16.tmp');tmp.write_text(out);tmp.replace(policy)
report={'environment':'dev1','decision_source':'business-location-exceptions.json','before_sha256':hashlib.sha256(before).hexdigest(),'after_sha256':hashlib.sha256(out.encode()).hexdigest(),'added_public_ids':added,'other_policy_rules_preserved':all(d[k]==json.loads(before)[k] for k in d if k!='business_exceptions')}
(root/'dev1-policy-receipt.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print('Applied scoped exceptions:',len(added))
