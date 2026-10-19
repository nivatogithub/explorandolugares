"""Rebuild the private editorial draft. Never imports or publishes."""
from pathlib import Path
import json, hashlib, zipfile, html
P=Path(__file__).resolve().parent
L=['es','en','pt-BR']; DATE='2026-10-19'
def save(n,v): (P/n).write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n')
S=[
('nacional','https://colombia.travel/es/zipaquira','ProColombia: contexto salinero e historia'),
('sal','https://colombia.travel/en/bogota/salt-cathedral-zipaquira','ProColombia: Catedral y plaza'),
('municipal','https://www.zipaquira.travel/wp-content/uploads/2026/02/Guia-Turistica-ZT.pdf','Secretaría de Desarrollo Económico y Turismo: guía municipal 2026'),
('departamento','https://eldoradoradio.cundinamarca.gov.co/provincias/sabana-centro/zipaquira/','Gobernación de Cundinamarca: patrimonio'),
('arqueologico','https://www.catedraldesal.gov.co/atracciones/museo-arqueologico/','Operador institucional: Museo Arqueológico'),
('minero','https://www.catedraldesal.gov.co/atracciones/ruta-del-minero/','Operador institucional: Ruta del Minero'),
('tren','https://turistren.com.co/','Operador: Tren de la Sabana'),
('diocesis','https://diocesisdezipaquira.org/parroquias/parroquia-la-catedral-diocesana-de-zipaquira/','Diócesis: Catedral Diocesana'),
('gabo','https://sidn.ramajudicial.gov.co/SIDN/PUBLICACIONES%20PERIODICAS/TEXTO%20COMPLETO%20Y%20TABLAS%20DE%20CONTENIDO/GACETA%20DEL%20CONGRESO/GACETA%20DEL%20CONGRESO%202023/GC%201696%20DE%202023.PDF','Gaceta del Congreso 1696/2023: Colegio de Gabo'),
('mapa-sal','https://www.catedraldesal.gov.co/wp-content/uploads/2025/03/MAPA-MINA_v05-interior-y-exterior.pdf','Operador: plano interior y exterior'),
('citytour','https://www.zipaquira.travel/page/2/','Portal municipal: recorrido guiado y puntos de información'),
]
sources=[dict(id=i,url=u,title=t,checked_at=DATE,type='primary',scope='Información pública; no prueba de operación, horarios o reservas actuales') for i,u,t in S]
src={s['id']:s for s in sources};save('fuentes.json',sources)
# Selection is an editorial recommendation, never an invented official ranking.
rows=[
('catedral-de-sal',['Catedral de Sal','Salt Cathedral','Catedral de Sal'],['Un templo subterráneo tallado en la roca salina.','An underground sanctuary carved into salt rock.','Um santuário subterrâneo escavado na rocha salina.'],['sal','nacional'],[5,5,5],'Icono inequívoco y principal motivador de viaje.'),
('plaza-comuneros',['Plaza de los Comuneros','Plaza de los Comuneros','Praça dos Comuneros'],['El corazón histórico, entre la catedral y edificios patrimoniales.','The historic heart, framed by the cathedral and heritage buildings.','O coração histórico, entre a catedral e edifícios patrimoniais.'],['sal','departamento'],[5,5,4],'Conjunto urbano central; engloba lectura exterior del palacio, sin otra tarjeta redundante.'),
('catedral-diocesana',['Catedral Diocesana','Diocesan Cathedral','Catedral Diocesana'],['El gran templo de piedra frente a la plaza principal.','The landmark stone church facing the main square.','O grande templo de pedra diante da praça principal.'],['diocesis'],[5,4,4],'Distinta de Catedral de Sal: interior y arquitectura propios.'),
('estacion-tren',['Estación del Tren','Railway Station','Estação Ferroviária'],['Arquitectura ferroviaria en la llegada del tren turístico.','Railway heritage at the tourist train’s arrival point.','Patrimônio ferroviário na chegada do trem turístico.'],['municipal','tren'],[4,5,4],'Ancla reconocible de llegada; edificio no equivale al viaje en tren.'),
('colegio-gabo',['El Colegio de Gabo','Gabo’s School','O Colégio de Gabo'],['La memoria de los años escolares de Gabriel García Márquez.','Discover the memory of Gabriel García Márquez’s school years.','A memória dos anos escolares de Gabriel García Márquez.'],['gabo'],[4,4,5],'Diferenciador literario de escala nacional; acceso a confirmar.'),
('plaza-independencia',['Plaza de la Independencia','Plaza de la Independencia','Praça da Independência'],['Una plaza para pasear entre cafés y cocina local.','A square for a stroll among cafés and local food.','Uma praça para passear entre cafés e gastronomia local.'],['municipal'],[4,5,3],'Vida urbana complementaria, no duplicado nominal de Comuneros.'),
('museo-quevedo',['Casa Museo Quevedo Zornoza','Quevedo Zornoza House Museum','Casa Museu Quevedo Zornoza'],['Una casa para acercarse al patrimonio musical de la ciudad.','A house devoted to the city’s musical heritage.','Uma casa para conhecer o patrimônio musical da cidade.'],['departamento','municipal'],[4,3,5],'Memoria musical propia; no confundir con Francisco de Quevedo.'),
('museo-arqueologico',['Museo Arqueológico','Archaeological Museum','Museu Arqueológico'],['Piezas y relatos para conocer el pasado prehispánico.','Objects and stories that explore the pre-Hispanic past.','Peças e relatos para conhecer o passado pré-hispânico.'],['arqueologico'],[3,4,5],'Contenido arqueológico documentado; no se rellena con otro templo.'),
]
experiences=[
('ruta-del-minero',['Ruta del Minero','Miner’s Route','Rota do Mineiro'],['Explora la extracción de sal con una actividad guiada.','Explore salt extraction through a guided activity.','Conheça a extração de sal em uma atividade guiada.'],['minero'],'oferta_operador_documentada'),
('tren-sabana',['Viajar en el Tren de la Sabana','Ride the Sabana Train','Viajar no Trem da Sabana'],['Llega en tren y descubre el paisaje de la sabana.','Arrive by train and discover the savanna landscape.','Chegue de trem e conheça a paisagem da savana.'],['tren'],'oferta_operador_documentada'),
('recorrido-historico',['Recorrer el centro histórico','Walk the historic centre','Percorrer o centro histórico'],['Conecta plazas, calles y patrimonio en una caminata.','Connect squares, streets and heritage on foot.','Conecte praças, ruas e patrimônio em uma caminhada.'],['citytour'],'editorial_autonoma'),
('sabores-locales',['Probar sabores locales','Taste local flavours','Provar sabores locais'],['Descubre amasijos y cocina de tradición salinera.','Discover traditional baked goods and salt-country cooking.','Descubra quitutes e a culinária da tradição salineira.'],['municipal'],'editorial_autonoma'),
('artesania-sal',['Descubrir la artesanía en sal','Discover salt crafts','Conhecer o artesanato em sal'],['Conoce piezas y oficios ligados a la identidad salinera.','Explore crafts connected to local salt-making identity.','Conheça peças e ofícios ligados à identidade salineira.'],['municipal'],'editorial_autonoma'),
]
def image(slug,names):
 return dict(file=f'images/{slug}-pendiente.webp',image_origin='ai_generated',image_creator='PENDIENTE: encargo ilustrado, todavía no generado ni aprobado',image_source_url='',image_license='Pendiente de generación y revisión; no es un activo existente',image_license_url='',alt={l:names[j]+' · '+['ilustración editorial prevista','planned editorial illustration','ilustração editorial prevista'][j] for j,l in enumerate(L)},caption={l:'' for l in L})
def item(row,typ):
 slug,names,desc,refs,*_=row
 return dict(slug=slug,type=typ,publication_status='draft',translations={l:dict(name=names[j],description=desc[j],external_url='',external_label='') for j,l in enumerate(L)},image=image(slug,names))
head=['Sal, patrimonio y vida de la sabana','Salt, heritage and life on the savanna','Sal, patrimônio e vida na savana']
intro=['Una catedral bajo tierra y un centro histórico para caminar.','An underground cathedral and a historic centre to explore on foot.','Uma catedral subterrânea e um centro histórico para percorrer a pé.']
hist=[
'La sal fue parte del intercambio muisca antes de la colonización española. La fundación colonial de Zipaquirá data de 1600. Su patrimonio salinero convive con plazas, arquitectura y la memoria escolar de Gabriel García Márquez; la actual Catedral de Sal abrió en 1995.',
'Salt supported Muisca exchange before Spanish colonisation. Zipaquirá’s colonial foundation dates to 1600. Its salt-making heritage shares the city with historic squares, architecture and memories of Gabriel García Márquez’s school years; the current Salt Cathedral opened in 1995.',
'O sal integrou as trocas muiscas antes da colonização espanhola. A fundação colonial de Zipaquirá data de 1600. Seu patrimônio salineiro convive com praças, arquitetura e a memória escolar de Gabriel García Márquez; a atual Catedral de Sal abriu em 1995.'
]
pack=dict(schema_version=2,destination=dict(slug='zipaquira',department='Cundinamarca',country_code='CO',destination_type='municipality',subregion='Sabana Centro',latitude=None,longitude=None,translations={l:dict(display_name='Zipaquirá',eyebrow='Cundinamarca · Colombia',headline=head[j],intro=intro[j],meta_description=intro[j]) for j,l in enumerate(L)},history={l:[hist[j]] for j,l in enumerate(L)},reasons={'es': ['Conocer el patrimonio salinero y recorrer el centro histórico.'], 'en': ['Discover salt-making heritage and explore the historic centre.'], 'pt-BR': ['Conhecer o patrimônio salineiro e percorrer o centro histórico.']},sources={l:[dict(title=s['title'],label=s['title'].split(':')[0],url=s['url'],section_key='history') for s in sources if s['id'] in ['nacional','sal','gabo']] for l in L}),hero_image=image('zipaquira-portada',['Zipaquirá: Catedral de Sal','Zipaquirá: Salt Cathedral','Zipaquirá: Catedral de Sal']),places=[item(r,'place') for r in rows]+[item(r,'experience') for r in experiences],local_research=dict(schema_version=1,researched_at=DATE,positioning='Patrimonio salinero, centro urbano y cultura local',competitors=[],potential_allies=[],business_prospects=[],acquisition_strategies=[],sources=[dict(url=s['url'],checked_at=DATE) for s in sources],notes=['Borrador privado. No hay alianzas, disponibilidad ni publicación aprobadas.']))

def apply_current_policy(pack):
    criteria=json.loads((P/'criterios-editoriales.json').read_text())
    allowed={x['slug'] for x in criteria['items'] if x['decision']=='include'}
    pack['places']=[x for x in pack['places'] if x['slug'] in allowed]
    titles={
      'ruta-del-minero':['Experiencia minera','Mining experience','Experiência de mineração'],
      'tren-sabana':['Paseo en tren turístico','Tourist train ride','Passeio de trem turístico'],
      'recorrido-historico':['Tour urbano patrimonial','Heritage walking tour','Passeio guiado pelo patrimônio']}
    descriptions={
      'ruta-del-minero':['Conoce la extracción de sal en una actividad organizada.','Discover salt extraction in an organised activity.','Conheça a extração de sal em uma atividade organizada.'],
      'tren-sabana':['Recorre en tren los paisajes de la sabana.','Travel through the savanna landscapes by train.','Percorra de trem as paisagens da savana.'],
      'recorrido-historico':['Descubre plazas y patrimonio en un recorrido guiado.','Discover squares and heritage on a guided walk.','Descubra praças e patrimônio em um passeio guiado.']}
    for p in pack['places']:
      if p['type']=='experience':
        for j,l in enumerate(L):
          p['translations'][l].update(name=titles[p['slug']][j],description=descriptions[p['slug']][j],external_url='',external_label='')
          p['image']['alt'][l]=titles[p['slug']][j]+[' · ilustración editorial',' · editorial illustration',' · ilustração editorial'][j]
    return pack
pack=apply_current_policy(pack)

save('paquete-destino.borrador.json',pack)
selection=[]
for i,r in enumerate(rows):
 a,b,c=r[4]
 if r[0]=='plaza-independencia':continue
 selection.append(dict(rank=len(selection)+1,slug=r[0],name=r[1][0],scores=dict(identity=a,visitor_relevance=b,distinctiveness=c),score=8*a+7*b+5*c,source_ids=r[3],reason=r[5],selection_status='propuesta_editorial_no_aprobada'))
save('auditoria-top8.json',dict(method='Valoración editorial 1–5: identidad 40%, relevancia visitante 35%, diferenciación 25%. Sin conteos oficiales de visitantes; no es ranking estadístico ni oficial. Desempate por integración al recorrido. Misma identidad no duplica un componente.',selection=selection,exclusions=[dict(name=n,reason=r) for n,r in [('Mina de Nemocón','Otro municipio; no adjudicar a Zipaquirá.'),('Parque Jaime Duque','Tocancipá; no pertenece al destino.'),('Parque de la Sal','Complejo que contiene Catedral y Museo; agrupar para evitar duplicación.'),('Centro histórico','Marco de Comuneros/Independencia, no novena tarjeta repetida.'),('Palacio Municipal','Integrado en Comuneros; acceso interior a confirmar.'),('Parque Villaveces','Complementario; menor diferenciación frente a los ocho seleccionados.'),('Monumento Egan Bernal','Complementario; no desplaza Catedral, estación y patrimonio literario.'),('Plaza de los Mártires','Complementaria; menos singular en una primera visita.'),('Finkana / Nukasa','Oferta de operador/naturaleza complementaria; vigencia y acceso deben validarse antes de entrar al Top8.')]],experience_classification=[dict(slug=r[0],source_ids=r[3],kind=r[4],availability_confirmed=False,partnership=False) for r in experiences],coverage_limit='No se afirma un censo exhaustivo nacional ni un orden original previamente aprobado. Selección local contrastada con guía institucional 2026 y fuentes primarias.'))
# Names only: no harvested contacts or inferred claim/operational status.
hotels=['Hotel Camino de la Sal','Hotel Estación Sabana','Hotel Cacique Real','Hotel Bacatá Plaza','Hotel Boutique MR','Hotel Salinero']
food=['Alma Coffee y Bakery','Andreabo Café y Pastelería','Brutal Gastronomy','Casa del Chorro','Indulto Bistro','La Gaceta Café Bar','La Komilona de Andrés','La Oveja y el Lobo','Labriego','El Político','Parrilla y Cervecería Pie de Cumbre','Rancho La Carreta','Salario','Sandwicheros','Tríada','El Zipa Café']
craft=['Nathagurumi','El Tesoro de las Artesanías','Arten Sal','El Mirlo','Emily Hecho a Mano','Sefhirot','Muebles Artesanos Vanima','Jizcasol']
businesses=[]
for cat,names,pages in [('hospedaje',hotels,'5–7'),('alimentacion',food,'8–15'),('comercio',craft,'23–24')]:
 for name in names:
  businesses.append(dict(name=name,category_code=cat,information_status='PUBLICO',summary='',description='',sources=[dict(url=src['municipal']['url'],checked_at=DATE,note=f'Guía institucional 2026, páginas impresas {pages}. Nombre y municipio documentados; operación, canales y reclamo sin verificar. Sin alianza ni contacto comercial. Ficha gratuita en borrador.')]))
save('paquete-negocios.json',dict(schema_version='business-import-v1',destination_slug='zipaquira',businesses=businesses))
with zipfile.ZipFile(P/'paquete-negocios.zip','w',zipfile.ZIP_DEFLATED) as z:z.write(P/'paquete-negocios.json','paquete-negocios.json')
briefs=[]
for typ,r in [('hero',('zipaquira-portada',['Portada Catedral de Sal'],[],['sal','mapa-sal']))]+[('place',r) for r in rows]+[('experience',r) for r in experiences]:
 slug=r[0]; briefs.append(dict(id=slug,role=typ,source_ids=r[3],style='EL-SEMI-REALISTA-1 · tinta y gouache semirrealista, color natural, sin filtro amarillo',framing='Portada 3:2; galería de nave subterránea solo tras confirmar eje, cruz y escala con referencias visuales.' if typ=='hero' else 'Tarjeta 3:2, foco en el elemento nombrado; documentar silueta y entorno antes de generar.',facts_verified_textually=True,visual_identity_verified=False,reference_photo_uploaded=False,generated=False,approved=False,blockers=['Inspección visual específica y encuadre factual pendientes; no generar un monumento por memoria.'],prompt_template='Ilustración editorial semirrealista de '+r[1][0]+' en Zipaquirá. Usar solo rasgos visuales confirmados, tinta y gouache, arquitectura reconocible, sin texto ni logos. No combinar escenas geográficamente distintas. Conservar ambiente de sabana andina, sin mar, palmeras tropicales ni catedrales europeas genéricas.'))
save('encargos-visuales.json',briefs)
check=dict(worker='Lugar 1',destination='zipaquira',branch='lugar/CO/cundinamarca/zipaquira',base_commit='8ff9dde',date=DATE,publication_approved=False,imported=False,preprod_reviewed=False,components=dict(research='entregado_con_fuentes',top8='propuesta_auditable',texts_es_en_pt='borrador_completo',history='breve_ancho_completo_sin_imagen',directory='30_fichas_borrador_importable',visual_briefs='14_encargos_preparados_sin_generar',images='0_de_14',destination_zip='bloqueado_por_imagenes',integration='pendiente_coordinador',human_review='pendiente'),next_action='Validar Top8 y encuadre de Catedral de Sal; completar imágenes por componente. El directorio puede importarse cuando exista el destino, siempre en borrador.')
save('checklist.json',check)
# Private standalone text review: does not pretend to be the live portal.
out=['<!doctype html><html lang="es"><meta charset="utf-8"><title>Zipaquirá · borrador editorial</title><style>body{font:16px system-ui;background:#fafafa;color:#172b42;max-width:1100px;margin:30px auto;padding:20px}section{border-top:1px solid #dadde2;padding:16px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}article{background:white;border:1px solid #dadde2;border-radius:12px;padding:16px}h2{font-size:22px}p{line-height:1.5}.pending{background:#f1f2f4;padding:12px}</style><h1>Zipaquirá</h1><p class="pending">BORRADOR EDITORIAL PRIVADO · Imágenes 0/14 · Sin importación, publicación ni aprobación.</p>']
for l in L:
 out += [f'<section><h2>{l}</h2><p>{html.escape(pack["destination"]["translations"][l]["intro"])}</p><h3>Historia</h3><p>{html.escape(pack["destination"]["history"][l][0])}</p>']
 for typ,title in [('place','Qué conocer'),('experience','Experiencias')]:
  out += [f'<h3>{title}</h3><div class="grid">']
  for p in pack['places']:
   if p['type']==typ:out += ['<article><strong>'+html.escape(p['translations'][l]['name'])+'</strong><p>'+html.escape(p['translations'][l]['description'])+'</p><small>Ilustración pendiente</small></article>']
  out += ['</div>']
 out += ['</section>']
out+=['<section><h2>Directorio gratuito · 30 fichas</h2><div class="grid">']+[f'<article>{html.escape(b["name"])} · {b["category_code"]}</article>' for b in businesses]+['</div></section></html>']
(P/'revision-editorial.html').write_text('\n'.join(out))
# Refresh internal eligibility audit without promoting operators publicly.
a=json.loads((P/'auditoria-top8.json').read_text());criteria=json.loads((P/'criterios-editoriales.json').read_text())
a['policy_revision']='19-10-2026: máximo ocho sin mínimo; experiencias solo con oferta real; oferentes internos'
a['experience_classification']=[dict(slug=e['slug'],decision=e['decision'],kind='oferta_operada_documentada' if e.get('offer_verified') else 'sugerencia_autonoma_excluida',source_urls=e['source_urls'],partnership=False,availability_confirmed=False) for e in criteria['items'] if e['kind']=='experience']
a['exclusions'].append(dict(name='Plaza de la Independencia',reason='Plaza complementaria del circuito urbano; se retira para evitar llenar ocho tarjetas con otro espacio de función solapada. No implica que sea inexistente ni esté cerrada.'))
save('auditoria-top8.json',a)
