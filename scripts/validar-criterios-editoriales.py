#!/usr/bin/env python3
"""Validate documented eligibility, not factual truth or human approval.
Usage: validar-criterios-editoriales.py MANIFEST.json CRITERIOS.json
"""
import json,sys
from urllib.parse import urlparse
from pathlib import Path

def validate(manifest,evidence):
 errors=[];items=evidence.get('items',[]);by_slug={x.get('slug'):x for x in items}
 if evidence.get('destination')!=manifest['destination']['slug']:errors.append('El expediente no corresponde al destino')
 if len(by_slug)!=len(items):errors.append('Criterios duplicados por slug')
 places=manifest.get('places',[]);attractions=[p for p in places if p.get('type')=='place']
 if len(attractions)>8:errors.append('Máximo ocho atractivos; no hay mínimo editorial')
 for p in places:
  slug=p['slug'];e=by_slug.get(slug,{})
  if e.get('decision')!='include':errors.append(slug+': falta decisión de inclusión sustentada')
  if e.get('kind')!=p.get('type'):errors.append(slug+': tipo inconsistente')
  if not str(e.get('purpose','')).strip():errors.append(slug+': falta finalidad de visita o servicio')
  urls=e.get('source_urls',[])
  if not urls or not all(urlparse(u).scheme=='https' and urlparse(u).netloc for u in urls):errors.append(slug+': fuentes HTTPS obligatorias')
  if not e.get('checked_at'):errors.append(slug+': fecha de comprobación ausente')
  if p.get('type')=='experience':
   o=e.get('operator',{})
   if e.get('offer_verified') is not True or not str(o.get('name','')).strip() or not o.get('offer_url') or not o.get('source_url'):errors.append(slug+': no acredita oferente y oferta concreta')
   elif not all(urlparse(o[k]).scheme=='https' and urlparse(o[k]).netloc for k in ['offer_url','source_url']):errors.append(slug+': URL de oferente inválida')
 return {'destination':manifest['destination']['slug'],'attractions':len(attractions),'experiences':len(places)-len(attractions),'errors':errors,'success':not errors,'human_approval':'not_inferred','factual_truth':'requires_source_review'}
if __name__=='__main__':
 m,e=[json.loads(Path(p).read_text()) for p in sys.argv[1:3]]
 r=validate(m,e);print(json.dumps(r,ensure_ascii=False,indent=2));sys.exit(0 if r['success'] else 1)
