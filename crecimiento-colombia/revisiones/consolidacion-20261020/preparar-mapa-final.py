#!/usr/bin/env python3
"""Build reviewable map only; no mutation of original snapshots or runtime."""
import argparse,pathlib,json,subprocess
p=argparse.ArgumentParser();p.add_argument('--resolution',type=pathlib.Path,required=True);p.add_argument('--snapshots',type=pathlib.Path,required=True);p.add_argument('--out',type=pathlib.Path,required=True);a=p.parse_args();d=json.loads(a.resolution.read_text());out={}
for slug in ['cartagena','bogota','medellin','santa-marta']:
 s=json.loads((a.snapshots/slug/'snapshot-public-content.json').read_text());t=s['tables'];assets={x['id']:x for x in t['media_assets']};translations={}
 for x in t['point_of_interest_translations']:
  if x['locale']=='es':translations[x['point_of_interest_id']]=x
 pois={x['slug']:x for x in t['points_of_interest'] if x['publication_status']=='published'};out[slug]={'hero':None,'places':{}}
 for r in d[slug]['selection']:
  selected=r['effective_static']
  if not selected or not selected['exists']:raise SystemExit('Missing resolved '+slug+'/'+r['slug'])
  poi=pois.get(r['slug']);name=translations.get(poi['id'],{}).get('name',r['slug']) if poi else slug
  asset={'path':selected['path'],'alt':'Ilustración editorial: '+name,'origin':'ai_generated','width':1200 if r['role']=='hero' else 800,'height':800 if r['role']=='hero' else 533,'caption':''}
  # Preserve exact DB metadata when selected is the existing DB asset, including new user references.
  if r['db'] and r['db']['sha256']==selected['sha256']:
   old=next((x for x in assets.values() if x['asset_path']==r['db']['path']),None)
   if old:
    asset.update({'origin':old['origin'],'width':old['width'],'height':old['height']})
  dims=json.loads(subprocess.check_output(['/opt/homebrew/bin/php','-r','echo json_encode(getimagesize($argv[1]));',selected['bytes_location']],text=True))
  asset['width'],asset['height']=dims['0'],dims['1']
  if r['role']=='hero':out[slug]['hero']=asset
  else:out[slug]['places'][r['slug']]=asset
 a.out.mkdir(parents=True,exist_ok=True)
(a.out/'destinos-semirrealistas-candidato.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
print('Candidate only; verify live DOM and dimensions before applying. No runtime writes.')
