#!/usr/bin/env python3
"""Explicit local package retirement. Does not remove originals or hosting files."""
from pathlib import Path
import argparse,json,re
RETIRED=['identidad-neutral.js','bogota-compacta.js','cartagena-compacta.js','medellin-compacta.js','santa-marta-compacta.js','destino-lectura-directa.js','nomenclatura-lugares.js','language-navigation.js','portada-atracciones.js','header-controls.js','header-controls.css','place-sections.js','portada.js','destinos-semirrealistas.json']
def apply(public):
 public=Path(public);pages=[p for p in public.rglob('*') if p.is_file() and p.suffix in {'.html','.php','.css','.js'}];retired=[]
 for name in RETIRED:
  asset=public/'assets'/name
  if not asset.exists():continue
  references=[]
  for p in pages:
   if p==asset or not p.exists() or p.name in RETIRED:continue
   text=p.read_text()
   if re.search(r'(?:src|href)=[\"\x27][^\"\x27]*assets/'+re.escape(name)+r'(?:[?\"\x27])',text):references.append(str(p.relative_to(public)))
  if references:raise ValueError('Retirado todavía referenciado: '+name+' '+str(references))
  asset.unlink();retired.append('assets/'+name)
 # This committed composition experiment is a private proposal, not a public page.
 proposal=public/'__composiciones-inicio/index.html'
 if proposal.exists():proposal.unlink();retired.append('__composiciones-inicio/index.html')
 (public.parent/'retired-resources.json').write_text(json.dumps({'hosting_changed':False,'removal_requires_review':True,'paths':retired},indent=2)+'\n')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);apply(p.parse_args().public)
