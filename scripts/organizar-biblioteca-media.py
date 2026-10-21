#!/usr/bin/env python3
"""Materialize approved media by content hash and rewrite exact active references. No DB/network/deletion."""
from pathlib import Path
import json,hashlib,shutil
R=Path(__file__).resolve().parents[1]
manifest_path=R/'backend/app/content/release-media-manifest.json'
manifest=json.loads(manifest_path.read_text());mapping={};unique={};reused=0
for item in manifest['media']:
 old=item['path'];source=R/'backend/public'/old
 if not source.is_file():raise RuntimeError('Falta '+old)
 data=source.read_bytes();sha=hashlib.sha256(data).hexdigest()
 if sha!=item['sha256']:raise RuntimeError('Hash distinto '+old)
 new='assets/media/'+sha[:2]+'/'+sha+source.suffix.lower();mapping[old]=new
 target=R/'backend/public'/new;target.parent.mkdir(parents=True,exist_ok=True)
 if target.exists():
  if hashlib.sha256(target.read_bytes()).hexdigest()!=sha:raise RuntimeError('Colisión '+new)
  reused+=1
 else:shutil.copy2(source,target)
 item['path']=new;unique[new]=item
# Replace only explicit known asset references in active text sources.
changed=[]
for base in [R/'backend/public',R/'backend/integration-medicion/public',R/'backend/app/content']:
 for f in base.rglob('*'):
  if f.suffix.lower() not in {'.php','.json','.js','.css','.html'}:continue
  s=f.read_text();original=s
  for old,new in mapping.items():s=s.replace(old,new).replace(old.replace('/','\\/'),new.replace('/','\\/'))
  if s!=original:f.write_text(s);changed.append(str(f.relative_to(R)))
f=R/'content/release-places-businesses.json';s=f.read_text()
for old,new in mapping.items():s=s.replace(old,new).replace(old.replace('/','\\/'),new.replace('/','\\/'))
f.write_text(s)
manifest['media']=list(unique.values());manifest_path.write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
report={'reference_count':len(mapping),'unique_files':len(unique),'already_present':reused,'bytes':sum((R/'backend/public'/p).stat().st_size for p in unique),'mapping':mapping,'updated_text_files':changed,'deleted_files':[],'production_changed':False}
out=R/'crecimiento-colombia/revisiones/validacion-ramas-20261020/biblioteca-media.json';out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print(json.dumps({k:v for k,v in report.items() if k not in ['mapping','updated_text_files']},ensure_ascii=False))
