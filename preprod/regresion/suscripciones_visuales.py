"""Contrato vigente: Gratuito + Destacado + Página Comercial + Premium."""
import re
TIERS=[('10','free','Gratuito'),('11','featured','Destacado'),('12','page','Página Comercial'),('13','page_premium','Premium')]
def run(case,check,get,pending=None):
 def grouping():
  code,body,*_=get('/lugares/jerico/');check(code==200,'No carga directorio')
  groups=re.findall(r'data-directory-tier="([^"]+)"',body)
  check(groups==['page','featured','free'],'Orden de bloques incorrecto: '+str(groups))
 case('SV-orden','Bloques Páginas comerciales (Premium con estrella) → Destacados → Gratuitos',grouping)
 for suffix,plan,label in TIERS:
  def test(suffix=suffix,plan=plan):
   code,body,*_=get('/lugares/jerico/');check(code==200,'No carga Jericó')
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
    if plan=='page_premium':
     for prefix,title,description in [('', 'Producto ficticio ', 'Descripción ficticia '),('/en', 'Fictional product ', 'Fictional description '),('/pt-br', 'Produto fictício ', 'Descrição fictícia ')]:
      status,localized,*_=get(prefix+'/negocios/'+uuid+'/');check(status==200,'No carga Premium en '+(prefix or 'es'))
      for channel in ['tel:+570000000000','https://wa.me/570000000001','mailto:premium@pruebas.invalid','https://example.invalid/instagram-prueba','https://example.invalid/sitio-prueba','https://example.invalid/facebook-prueba','https://example.invalid/linkedin-prueba']:
       check('href="'+channel+'"' in localized,'Falta canal de contacto Premium en '+(prefix or 'es'))
      products=re.findall(r'<article\s+class="business-product"[^>]*>.*?</article>',localized,re.S)
      check(len(products)==5,'Premium debe mostrar cinco productos en '+(prefix or 'es'))
      images=[]
      for index,product in enumerate(products,1):
       check(title+str(index) in product and description+str(index) in product,'Faltan textos del producto '+str(index)+' en '+(prefix or 'es'))
       image=re.search(r'<img\b[^>]*\bsrc="([^"]+)"',product);check(image,'Falta imagen del producto '+str(index));images.append(image.group(1))
       image_status,*_=get(image.group(1));check(image_status==200,'No carga la imagen del producto '+str(index))
      check(len(set(images))==5,'Los productos deben conservar sus cinco imágenes')
    else:check('business-products-grid' not in page,'Página normal muestra productos exclusivos Premium')
  case('SV-'+plan,label+': presentación y acceso según suscripción',test)
 if pending:

  pending('SV-operador','Complemento de operador separado de suscripción','El precio se consulta de las tarifas configuradas. La contratación, revisión y publicación se comprobaron en preproducción; este conjunto HTTP aún no automatiza el recorrido visual de la versión candidata.')
