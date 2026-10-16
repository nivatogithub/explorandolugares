#!/usr/bin/env python3
"""Reconcile only additive, non-overlapping remote manifest updates; reject actual file conflicts."""
import importlib.util,json,tempfile,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('package',ROOT/'scripts/preparar-cobertura.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
c=m.sftp.load_config(ROOT.parent/'explorandolugares/.local/sftp.json');previousPath=ROOT/'.local/analytics-base/manifest.json';old=json.loads(previousPath.read_text());client=m.sftp.SFTP(c)
with tempfile.TemporaryDirectory() as temp:
 p=Path(temp)/'manifest.json';client.run([f'get {m.sftp.quote(c["remote_base"]+"/"+m.NAME)} {m.sftp.quote(p)}']);new=m.manifest(json.loads(p.read_text()),c)
 a={f['relative']:f['sha256'] for f in old['files']};b={f['relative']:f['sha256'] for f in new['files']};ours={d for s,d in m.sources()};changed={k for k in a.keys()|b.keys() if a.get(k)!=b.get(k)}
 print('Remote manifest changes:',len(changed),'entries')
 overlap=changed&ours
 if overlap:
  # A concurrent partial release can discard index entries without touching their files.
  # Verify every overlapping artifact against our last confirmed release before repairing metadata.
  commands=[]
  for relative in sorted(overlap):
   if relative not in a:raise RuntimeError('Unknown prior integration artifact')
   dst=Path(temp)/relative;dst.parent.mkdir(parents=True,exist_ok=True);commands.append(f'get {m.sftp.quote(m.target(c,relative))} {m.sftp.quote(dst)}')
  client.run(commands)
  for relative in overlap:
   if hashlib.sha256((Path(temp)/relative).read_bytes()).hexdigest()!=a[relative]:raise RuntimeError('Actual remote file conflict; no overwrite: '+relative)
  check=Path(temp)/'recheck.json';client.run([f'get {m.sftp.quote(c["remote_base"]+"/"+m.NAME)} {m.sftp.quote(check)}'])
  if json.loads(check.read_text())!=new:raise RuntimeError('Remote changed during reconciliation')
  merged={f['relative']:f for f in new['files']}
  for f in old['files']:
   if f['relative'] in ours:merged[f['relative']]=f
  new={**new,'files':sorted(merged.values(),key=lambda f:f['relative']),'file_count':len(merged),'coverage_component_commit':old['commit']}
  p.write_text(json.dumps(new,ensure_ascii=False,indent=2)+'\n');client.run([f'put {m.sftp.quote(p)} {m.sftp.quote(c["remote_base"]+"/"+m.NAME)}'])
  print('Repaired stale index only; all overlapping files matched confirmed deployment')
 previousPath.write_text(p.read_text())
 shared=ROOT.parent/'explorandolugares/.local/deployment-manifest.json'
 # Synchronize publication bookkeeping only. Never change another branch's source files.
 if overlap and shared.exists() and json.loads(shared.read_text())==json.loads(check.read_text()):shared.write_text(p.read_text())
 print('Reconciled manifest while preserving unrelated publication')
