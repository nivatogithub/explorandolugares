"""Assemble the complete local-review ZIP from real, inspected generated assets."""
from pathlib import Path
import json, hashlib, zipfile
P=Path(__file__).resolve().parent; I=P/'imagenes'
read=lambda p:json.loads(p.read_text())
assets=read(I/'activos.json'); prompts={p['id']:p['prompt'] for p in read(I/'prompts.json')+read(I/'prompts-correcciones.json')}
assert assets, 'No assets available'
manifest=read(P/'paquete-destino.borrador.json')
usage=[('zipaquira-portada',manifest['hero_image'])]+[(p['slug'],p['image']) for p in manifest['places']]
assetmap={a['id']:a for a in assets}; provenance=[]
for key,img in usage:
 a=assetmap[key]; web=a['web'][-1]; img.update(file=f'images/{key}.webp',image_origin='ai_generated',image_creator='Ilustración independiente generada con herramienta integrada imagegen; revisión humana pendiente',image_license='Salida IA propuesta para Explorando Lugares; no contiene fotografía de terceros de entrada',image_source_url='',image_license_url='')
 for lang in ['es','en','pt-BR']:
  img['alt'][lang]=img['alt'][lang].replace(' prevista','').replace('planned ','')
  img['caption'][lang]=''
 provenance.append(dict(id=key,tool='builtin image_gen',model='No expuesto por herramienta',prompt=prompts[key],created_at='2026-10-19',master=a,role='hero' if key=='zipaquira-portada' else next(p['type'] for p in manifest['places'] if p['slug']==key),factual_source_ids=next(b['source_ids'] for b in read(P/'encargos-visuales.json') if b['id']==key),reference_photos_uploaded=[],independent_composition=True,human_approved=False,status='draft_pending_human_review',limits='Interpretación ilustrada; no prueba de condición actual ni reproducción topográfica exacta. Las escenas temáticas no representan salas o piezas catalogadas exactas.'))
(P/'paquete-destino.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
with zipfile.ZipFile(P/'paquete-destino.zip','w',zipfile.ZIP_DEFLATED) as z:
 z.write(P/'paquete-destino.json','paquete-destino.json')
 for key,img in usage:z.write(I/assetmap[key]['web'][-1]['file'],img['file'])
(I/'procedencia.json').write_text(json.dumps(provenance,ensure_ascii=False,indent=2)+'\n')
check=read(P/'checklist.json');check['components'].update(visual_briefs=f'{len(usage)}_encargos_activos',images=f'{len(usage)}_de_{len(usage)}_borradores',destination_zip='completo_pendiente_validacion_nativa',integration='pendiente_coordinador',human_review='pendiente')
check['next_action']='Importar paquete completo en preproducción como borrador y revisar las imágenes/textos con el propietario. No publicar producción.'
(P/'checklist.json').write_text(json.dumps(check,ensure_ascii=False,indent=2)+'\n')
briefs=[b for b in read(P/'encargos-visuales.json') if b['id'] in {key for key,img in usage}]
for b in briefs:b.update(generated=True,approved=False,blockers=['Revisión humana del resultado pendiente'],image_web=assetmap[b['id']]['web'][-1]['file'])
(P/'encargos-visuales.json').write_text(json.dumps(briefs,ensure_ascii=False,indent=2)+'\n')
# Update standalone editorial review; no API or app integration involved.
page=(P/'revision-editorial.html').read_text().replace('Imágenes 0/14',f'Imágenes {len(usage)}/{len(usage)} · resultados pendientes de aprobación')
if 'src="imagenes/web/zipaquira-portada-1200.webp"' not in page:page=page.replace('<h1>Zipaquirá</h1>','<h1>Zipaquirá</h1><img style="width:100%;aspect-ratio:3/2;object-fit:cover" src="imagenes/web/zipaquira-portada-1200.webp" alt="Propuesta ilustrada de Catedral de Sal">')
for key,img in usage[1:]:
 place=next(p for p in manifest['places'] if p['slug']==key)
 for l in ['es','en','pt-BR']:
  import html
  needle='<article><strong>'+html.escape(place['translations'][l]['name'])+'</strong>'
  replacement='<article><img style="width:100%;aspect-ratio:3/2;object-fit:cover;border-radius:8px" src="imagenes/web/'+key+'-640.webp" alt="'+html.escape(img['alt'][l])+'"><strong>'+html.escape(place['translations'][l]['name'])+'</strong>'
  page=page.replace(needle,replacement)
page=page.replace('<small>Ilustración pendiente</small>','<small>Ilustración IA · revisión pendiente</small>')
(P/'revision-editorial.html').write_text(page)
print('Complete destination ZIP: '+str((P/'paquete-destino.zip').stat().st_size)+' bytes')
