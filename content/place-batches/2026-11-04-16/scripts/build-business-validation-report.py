"""Summarize documented coordinates without equating them with verified businesses."""
from pathlib import Path
import json, collections
ROOT=Path(__file__).resolve().parents[1]
summary=[]; pending=[]; statuses=collections.Counter()
for f in sorted((ROOT/'places').glob('*/research.json')):
 d=json.loads(f.read_text()); rows=[]
 for b in d['businesses']:
  v=b.get('editorial_validation',{});g=b.get('geolocation');statuses[v.get('live_coordinate_validation','not_checked')]+=1
  if g:
   assert isinstance(g['latitude'],(int,float)) and -90<=g['latitude']<=90
   assert isinstance(g['longitude'],(int,float)) and -180<=g['longitude']<=180
  else:pending.append({'place':d['name'],'name':b['name'],'address':b.get('address'),'source_url':b.get('source_url'),'issues':v.get('issues',[]),'candidate_coordinates':b.get('geolocation_candidates',[])})
  rows.append({'name':b['name'],'category':b['category'],'geolocation':g,**v})
 (f.parent/'business-validation.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
 summary.append({'place':d['name'],'records':len(rows),'with_coordinates':sum(bool(r['geolocation']) for r in rows),'coordinates_missing':sum(not r['geolocation'] for r in rows)})
report={'checked_at':'2026-11-04','records':sum(r['records'] for r in summary),'with_coordinates':sum(r['with_coordinates'] for r in summary),'coordinates_missing':len(pending),'previous_audit':{'records':434,'missing_coordinates_before':70,'coordinates_added':36,'missing_coordinates_after':34},'validation_statuses':dict(statuses),'places':summary,'pending':pending}
(ROOT/'business-validation-summary.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
lines=['# Negocios y georreferenciación — 4 de noviembre de 2026','',f"{report['records']} registros asociados; no equivale a negocios verificados ni listos para publicación.",'','## Resultado',f"- Estado actual: {report['with_coordinates']} registros con coordenadas documentadas; {len(pending)} sin punto suficientemente respaldado. El inventario puede crecer con nuevos negocios; no mezclar este saldo con los 70 pendientes de la auditoría anterior.",'- Los 14 registros anteriores fueron contrastados: 13 coinciden con sus fuentes, incluyendo variantes de nombre revisadas; Asomi coincide como punto, pero su condición de negocio turístico no está confirmada.','- Las 31 áreas de OpenStreetMap señaladas en la revisión anterior siguen pendientes de contraste geométrico.','- Un punto corresponde a la oficina externa de Providencia Travel en San Andrés, no a una sede en Providencia.','- Las coordenadas documentadas no certifican entradas exactas ni operación actual.','','| Lugar | Registros | Con coordenadas | Sin coordenadas |','|---|---:|---:|---:|']
for r in summary:lines.append(f"| {r['place']} | {r['records']} | {r['with_coordinates']} | {r['coordinates_missing']} |")
lines+=['','## Correcciones y conflictos','- Directorio municipal de Inírida: los registros sí están en sus datos públicos incrustados. Se retiró la observación incorrecta de ausencia de fichas.','- Asomicamping: la fuente se llama Asomi; se conservó el nombre anterior como antecedente y se bloqueó su confirmación comercial.','- Playa Alegre: punto de directorio secundario rechazado por corresponder al núcleo de Bahía Solano y no a El Valle.','- Hotel Orinoco Real: dos fuentes difieren alrededor de 56 m; ambos candidatos permanecen documentados sin elegir arbitrariamente.','- Hotel Portal Real: ubicación de Guest Reservations compatible con referencia histórica del predio; punto discrepante de Kayak conservado como conflicto.','- Hotel Safari: la coordenada hallada en un documento de CDA pertenece a una planta de tratamiento vecina; no se asignó al hotel.','- Melo Hostel: se documentó anuncio de cierre el 16 de noviembre de 2026 en el sitio del operador; revisar antes de publicar.','','## Pendientes individuales']
for r in pending:lines.append(f"- **{r['place']} — {r['name']}**: "+(r['address'] or 'sin dirección precisa confirmada')+'. Falta punto nombrado fiable.')
lines+=['','No se desplegaron cambios, no se contactó a negocios y no se declararon verificados ni aliados.']
(ROOT/'BUSINESS_VALIDATION.md').write_text('\n'.join(lines)+'\n')
print(json.dumps({k:v for k,v in report.items() if k not in ['pending','validation_statuses']},ensure_ascii=False))
