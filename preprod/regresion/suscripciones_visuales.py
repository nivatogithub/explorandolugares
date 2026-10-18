"""Contrato vigente: Gratuito + Destacado + Página Comercial + Premium."""
import re
TIERS=[('10','free','Gratuito'),('11','featured','Destacado'),('12','page','Página Comercial'),('13','page_premium','Premium')]
def run(case,check,get,pending=None):
 def grouping():
  code,body,*_=get('/destinos/jerico/');check(code==200,'No carga directorio')
  groups=re.findall(r'data-directory-tier="([^"]+)"',body)
  check(groups==['page','featured','free'],'Orden de bloques incorrecto: '+str(groups))
 case('SV-orden','Bloques Páginas comerciales (Premium con estrella) → Destacados → Gratuitos',grouping)
 for suffix,plan,label in TIERS:
  def test(suffix=suffix,plan=plan):
   code,body,*_=get('/destinos/jerico/');check(code==200,'No carga Jericó')
   uuid='f00d0000-0000-4000-8000-0000000000'+suffix
   m=re.search(r'<article\b[^>]*id="negocio-'+uuid+r'"[^>]*>.*?</article>',body,re.S);check(m,'Falta negocio de prueba '+plan);card=m[0]
   check('directory-card-tier-'+plan in card,'Nivel incorrecto: '+plan)
   if plan=='free':check('<a ' not in card and 'directory-description' not in card and 'directory-featured-badge' not in card,'Gratis expone información o beneficios pagos')
   if plan=='featured':check('directory-featured-badge' in card and 'is-featured' in card,'Destacado no se resalta')
   if plan in ['page','page_premium']:check('/negocios/'+uuid+'/' in card,'No hay enlace a página del negocio')
   if plan=='page':check('directory-featured-badge' not in card,'Página Comercial recibe destacado no previsto')
   if plan=='page_premium':check('directory-premium-star' in card and 'aria-label="Premium"' in card,'Falta distinción Premium')
   status,page,*_=get('/negocios/'+uuid+'/');check(status==(200 if plan in ['page','page_premium'] else 404),'Acceso a página no corresponde al nivel: '+str(status))
   if status==200:
    check('Negocio Ficticio' in page,'Página no corresponde a fixture')
    if plan=='page_premium':check('La historia del negocio' in page and 'Servicios destacados' in page,'Faltan secciones Premium')
    else:check('La historia del negocio' not in page,'Página normal muestra sección exclusiva Premium')
  case('SV-'+plan,label+': presentación y acceso según suscripción',test)
 if pending:

  pending('SV-operador','Complemento de operador separado de suscripción','Implementado a $29.900/mes con suscripción paga, una experiencia publicada del mismo lugar. Dominio y contratación/revisión/renderizado se prueban en backend/tests/experience-operator-addon.php y admin-pages.integration.py. Falta automatizar su recorrido visual de aceptación en este conjunto.')
