"""QA reproducible de borradores textuales y clasificación. No red, fotos ni importación."""
from pathlib import Path
import json,re
p=Path(__file__).resolve().parent
load=lambda n:json.loads((p/n).read_text())
e=load('experiencias-v6.json')['experiences'];d=load('directorio.json');g=load('nuevos-atractivos-top8-borrador.json')['items'];c=load('CLASIFICACION_LUGARES_EXPERIENCIAS.json');a=load('AUDITORIA_TOP8.json');langs={'es','en','pt-BR'}
checks=[]
def check(name,ok,detail=''):
 checks.append({'check':name,'pass':bool(ok),'detail':detail});assert ok,(name,detail)
check('counts',len(e)==9 and len(d)==11 and len(g)==3,'9 experiencias,11 directorios,3 borradores geográficos ampliados')
for label,rows in [('experiences',e),('directory',d),('geographic_drafts',g)]:
 check(label+'_unique_ids',len({r['id'] for r in rows})==len(rows))
 check(label+'_es_en_pt',all(set(r.get('translations',{}))==langs and all(r['translations'][l].get('name','').strip() and r['translations'][l].get('description','').strip() for l in langs) for r in rows))
 check(label+'_sources',all(str(r.get('source_url',r.get('source',''))).startswith('https://') for r in rows))
 texts=[t['name']+' '+t['description'] for r in rows for t in r['translations'].values()]
 check(label+'_no_prices_timetables',not any(re.search(r'\$|€|\bCOP\b|\b\d{1,2}:\d{2}\b|\b\d+\s*(?:USD|euros|pesos)\b',t,re.I) for t in texts))
 check(label+'_no_workflow_in_public_copy',not any(re.search(r'\b(?:location_id|schema_version|borrador|preprod|pending|worker)\b',t,re.I) for t in texts))
locs={r['location_id'] for r in c['locations']};check('experience_locations',all(r.get('location_id') in locs or r['id']=='moto-acuatica' and r.get('location_id') is None for r in e));check('related_locations',all(all(x in locs for x in r.get('related_location_ids',[])) for r in e));check('experiences_not_places',all(r['content_type']=='experience' and r['counts_as_top8_place'] is False for r in e));check('no_invented_events',not c.get('events') and all(r.get('event_date') is None for r in e));check('moto_not_offer',any(r['id']=='moto-acuatica' and r['operator'] is None and r['location_id'] is None and not r['availability_verified'] and r.get('operator_validation_status')=='pending' for r in e));check('no_implied_booking_or_partnership',all(not r.get('availability_verified') and not r.get('partnership') for r in e));check('acuario_as_experience',any(r['id']=='visita-acuario' for r in e) and any(r['id']=='CO-47-santa-marta-acuario-rodadero' for r in d));check('seven_places_eighth_pending',sum(bool(r['included']) for r in a['matrix'])==7 and a['eighth_cutoff_status']=='pending_human_editorial_decision');check('tayrona_before_rodadero',next(r['proposed_order'] for r in a['matrix'] if r['id']=='tayrona')<next(r['proposed_order'] for r in a['matrix'] if r['id']=='rodadero'));check('all_candidates_have_reasons',all(r.get('comparison_with_eighth') and r.get('sources') for r in a['matrix']));check('taganga_minca_rodadero_distinct',all('santa-marta:'+slug in locs for slug in ['taganga','minca','rodadero']));check('all_draft',all(r.get('publication_status')=='draft' for r in e+d));check('approval_fields_well_typed',all(isinstance(r.get('text_approval'),bool) and isinstance(r.get('image_approval'),bool) for r in e))
report={'date':'2026-10-17','state':'PASS_textual_draft_qa_not_publication','checks':checks,'counts':{'experiences':len(e),'directory':len(d),'expanded_geographic_drafts':len(g),'locations':len(c['locations']),'top8_proposed':7,'eighth_pending':True,'events':0,'languages':sorted(langs)},'photos_processed':False,'network_used_by_validator':False,'database_changed':False,'package_changed':False}
(p/'QA_CONTENIDO_SIN_FOTOS.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print('PASS:',len(checks),'verificaciones;9 experiencias/11 directorios/3 idiomas; 7 lugares propuestos y octavo pendiente.')
