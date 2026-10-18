"""Keep reasons distinct from attraction descriptions; preserve full research."""
from pathlib import Path
import json,hashlib,zipfile
P=Path(__file__).resolve().parent
reasons={
'es':['Caminar entre murallas, plazas y balcones del centro histórico.','Descubrir Getsemaní, su vida de barrio y sus expresiones culturales.','Conocer la historia caribeña a través de fortalezas y museos.','Combinar ciudad y mar con una excursión a Barú o las Islas del Rosario.'],
'en':['Walk among the historic centre’s walls, squares and balconies.','Discover Getsemaní, its neighbourhood life and cultural expressions.','Explore Caribbean history through fortresses and museums.','Combine city and sea with a trip to Barú or the Rosario Islands.'],
'pt-BR':['Caminhar entre muralhas, praças e varandas do centro histórico.','Conhecer Getsemaní, sua vida de bairro e suas expressões culturais.','Descobrir a história caribenha em fortalezas e museus.','Combinar cidade e mar com um passeio a Barú ou às Ilhas do Rosário.']}
p=P/'paquete-v2.json';d=json.loads(p.read_text());old=d['destination']['reasons'];d['destination']['reasons']=reasons;p.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
with zipfile.ZipFile(P/'cartagena-borrador-v2.zip','w',compression=zipfile.ZIP_DEFLATED) as z:
 z.write(p,'paquete-destino.json')
 for m in [d['hero_image'],*[i['image'] for i in d['places']]]:z.write(P/'imagenes/derivados'/Path(m['file']).name,m['file'])
report={'request':'Acortar verticalmente Por qué visitar; reemplazar cinco referencias; generar portada con skill existente.','reasons_before_chars':{l:sum(map(len,v)) for l,v in old.items()},'reasons_after_chars':{l:sum(map(len,v)) for l,v in reasons.items()},'reasons_after':reasons,'reason_approval':'pending_human_review','reference_replacements':['centro-historico','san-felipe','getsemani','islas-rosario','la-boquilla'],'references_conserved_provisionally':['playa-blanca-baru','la-popa','muhca'],'conserved_is_final_approval':False,'hero_generation_authorized':True,'hero_final_approved':False,'production_authorized':False,'layout_decision':'Cuatro motivos breves, sin duplicar descripciones de ocho atractivos. Imagen de historia opcional y relacionada; no imagen decorativa obligatoria en motivos.'}
(P/'correcciones-v3.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(report['reasons_before_chars'],report['reasons_after_chars'])
