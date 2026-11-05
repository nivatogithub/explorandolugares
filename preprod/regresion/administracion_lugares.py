"""Accesos del panel y contenido público; usa solo GET y la cuenta sintética administradora existente."""
import html,json,re,urllib.parse
from html.parser import HTMLParser
class Links(HTMLParser):
 def __init__(self):super().__init__();self.items=[];self.current=None
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='a' and 'href' in a:self.current={'href':a['href'],'text':''};self.items.append(self.current)
 def handle_data(self,text):
  if self.current:self.current['text']+=text
 def handle_endtag(self,tag):
  if tag=='a':self.current=None

def run(env):
 case,check,pending=env['case'],env['check'],env['pending'];Client=env['Client'];ROOT=env['ROOT'];csrf=env['csrf'];query=env['query'];anon=env['anon'];person=env['person'];BASE=env['BASE']
 admin=Client();person=Client()
 def ordinarylogin():
  info=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles']['viajero'];_,page,_=person.get('/negocios/acceso/');code,_,_=person.request('/negocios/acceso/',{'csrf':csrf(page),'email':info['email'],'password':info['password']});check(code==200 and person.state().get('authenticated') and not person.state().get('administrator'),'No se pudo preparar cuenta autenticada sin permisos')
 case('AP-00','Preparar viajero autenticado sin permisos administrativos',ordinarylogin)
 expected=[('index.php?tab=solicitudes','Solicitudes de participación'),('businesses.php','Negocios'),('business-contacts.php','Canales y responsable'),('update-businesses.php','Exportar / actualizar'),('campaigns.php','Campañas por correo'),('claims.php','Reclamos de negocio'),('account-applications.php','Solicitudes de cuenta'),('accounts.php','Cuentas'),('subscriptions.php','Suscripciones'),('places.php','Destinos'),('investors.php','Inteligencia e inversión'),('costs.php','Costos y cancelaciones'),('index.php?tab=sistema','Estado del sistema'),('database.php','Base de datos')]
 cache={}
 def adminlogin():
  info=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles']['administrador'];_,page,_=admin.get('/negocios/acceso/');code,page,_=admin.request('/negocios/acceso/',{'csrf':csrf(page),'email':info['email'],'password':info['password']});check(code==200 and admin.state().get('administrator'),'No puede iniciar sesión el administrador de pruebas')
 case('AD-00','Administrador de pruebas inicia sesión con su rol',adminlogin)
 def access(route,label):
  check(admin.state().get('administrator'),'No hay sesión administradora válida')
  code,page,url=admin.get('/admin/'+route);check(code==200,'HTTP '+str(code)+' al abrir '+route)
  check('/negocios/acceso/' not in url and 'admin-sidebar' in page,'Acceso termina en login o sin panel: '+route)
  check(not re.search(r'(Fatal error|Uncaught .*Exception|SQLSTATE\[)',page),'Error de ejecución en '+route);cache[route]=page
 for n,(route,label) in enumerate(expected,1):case('AD-'+str(n).zfill(2),'Administrador accede a '+label,lambda route=route,label=label:access(route,label))
 def nav_integrity():
  page=cache.get('businesses.php','');check(page,'Falta respuesta del panel de negocios')
  sidebar=re.search(r'<aside\b.*?</aside>',page,re.S);check(sidebar,'No existe navegación administrativa');parser=Links();parser.feed(sidebar[0]);actual={x['href'] for x in parser.items}
  actual={urllib.parse.urlsplit(urllib.parse.urljoin(BASE+'/admin/businesses.php',href)).path+('?' +urllib.parse.urlsplit(href).query if urllib.parse.urlsplit(href).query else '') for href in actual}
  # The current sidebar groups workflows; individual tools remain covered by AD/AP.
  for route in ['business-support.php','index.php?tab=solicitudes','accounts.php','subscriptions.php','email-templates.php','campaigns.php','places.php','reports.php','investors.php','costs.php','cancellations.php','payment-settings.php','settings.php','campaigns.php?tab=conexion','index.php?tab=sistema','incidents.php','database.php']:
   check('/admin/'+route in actual,'Flujo perdido del menú: '+route)
  for item in parser.items:
   href=item['href']
   if not href or href.startswith(('#','http','mailto:')):continue
   url=urllib.parse.urlsplit(urllib.parse.urljoin(BASE+'/admin/businesses.php',href));check(url.netloc=='127.0.0.1:8088','Menú apunta fuera de preprod');code,page,_=admin.get(url.path+('?' +url.query if url.query else ''));check(code==200,'Otra opción del menú falla: '+item['text'].strip())
 case('AD-15','Ninguna opción del menú desaparece; probar también enlaces adicionales',nav_integrity)
 def forbidden(route):
  for label,c in [('anónimo',anon),('persona sin rol',person)]:
   code,page,url=c.get('/admin/'+route)
   check(code in [401,403] or ('admin-sidebar' not in page and ('Inicia sesión' in page or '/negocios/acceso/' in url)),'Acceso administrativo expuesto a '+label+': '+route)
 for n,(route,label) in enumerate(expected,1):case('AP-'+str(n).zfill(2),'Denegar '+label+' a visitantes y cuentas sin rol',lambda route=route:forbidden(route))
 def extra_pages():
  # Document/resource endpoints need real IDs and their own authorization assertions; do not count missing ID as success.
  for path in ['import-businesses.php','transfer-businesses.php','analytics.php','reports.php','public-requests.php','settings.php','payment-settings.php']:
   code,page,_=admin.get('/admin/'+path);check(code==200 and re.search(r'<h1\b',page) and (path=='reports.php' or '<form' in page or path=='public-requests.php') and not re.search(r'(Fatal error|SQLSTATE\[)',page),'No abre herramienta complementaria '+path+'; HTTP '+str(code));forbidden(path)
 case('AD-16','Acceso a importación, traslado de negocios y medición',extra_pages)
 if any('Próximamente' in p and 'Reportes' in p for p in cache.values()):pending('AD-17','Reportes administrativos funcionales','El menú todavía anuncia Próximamente. No se contabiliza como módulo disponible.')
 def isolated_operations():
  from admin_operations import run as operations
  operations(env,admin)
 case('AD-18','Operaciones administrativas HTTP/MariaDB con registros sintéticos propios y limpieza',isolated_operations)
 def adminlogout():
  state=admin.state();admin.request('/negocios/salir/',{'csrf':state['csrf']});check(not admin.state()['authenticated'],'No cerró sesión administradora');code,page,url=admin.get('/admin/accounts.php');check(code in (401,403) or ('/negocios/acceso/' in url and 'admin-sidebar' not in page),'Cerrar sesión no revoca acceso administrativo')
 case('AD-19','Salida del administrador revoca acceso al panel',adminlogout)
 published=query('SELECT d.slug,SUM(CASE WHEN p.poi_type="experience" AND p.publication_status="published" THEN 1 ELSE 0 END) AS experience_count FROM destinations d LEFT JOIN points_of_interest p ON p.destination_id=d.id WHERE d.publication_status="published" GROUP BY d.id,d.slug ORDER BY d.slug');check(published,'No hay lugares publicados para recorrer')
 experience_inventory=[]
 def listing():
  paths=set();seen=set();queue=['/lugares/']
  while queue:
   route=queue.pop(0)
   if route in seen:continue
   seen.add(route);check(len(seen)<=100,'Paginación inesperada o circular')
   code,page,_=anon.get(route);check(code==200,'No abre listado de lugares: '+route)
   parser=Links();parser.feed(page)
   for item in parser.items:
    url=urllib.parse.urlsplit(urllib.parse.urljoin(BASE+route,item['href']))
    if url.netloc!='127.0.0.1:8088':continue
    paths.add(url.path)
    params=urllib.parse.parse_qs(url.query)
    if url.path=='/lugares/' and set(params)=={'pagina'} and params['pagina'][0].isdigit():queue.append(url.path+'?'+url.query)
  for row in published:check('/lugares/'+row['slug']+'/' in paths,'Lugar publicado sin entrada en listado: '+row['slug'])
 case('LU-01','Listado público permite encontrar todos los lugares publicados',listing)
 for row in published:
  slug=row['slug'];page_cache={}
  def page(slug=slug,page_cache=page_cache):
   if not page_cache:
    code,body,_=anon.get('/lugares/'+slug+'/');check(code==200,'Lugar publicado devuelve HTTP '+str(code));page_cache['html']=body
   return page_cache['html']
  def history(page=page):
   body=page();m=re.search(r'<section\b[^>]*id="descubre"[^>]*>(.*?)</section>',body,re.S);check(m,'Falta sección de historia');text=html.unescape(re.sub('<[^>]+>',' ',m[1]));check(len(text.strip())>100,'Historia ausente o insuficiente')
  def directory(page=page):
   body=page();check(all(x in body for x in ['id="lugares"','id="catalogo-local"','id="directory-panel"']),'Falta directorio de comercios');check('href="#lugares"' in body,'Directorio sin enlace interno');check('directory-tab-todos' in body,'Falta filtro de todos los comercios')
  def experiences(page=page,slug=slug,expected=int(row['experience_count'] or 0)):
   body=page()
   shown=len(re.findall(r'<article\b[^>]*class="destination-experience-card"',body))
   check(shown==expected,'Experiencias publicadas distintas de BD: '+slug+'; esperadas '+str(expected)+', mostradas '+str(shown))
   experience_inventory.append({'slug':slug,'published_experiences':expected,'rendered_experiences':shown})
   if slug=='jerico':
    check('id="experiencias-locales"' in body,'No aparecen experiencias de Jericó');check(body.index('id="atractivos-destino"')<body.index('id="experiencias-locales"')<body.index('id="lugares"'),'Experiencias fuera de su posición después de atractivos');check(body.count('destination-experience-card')>=3,'Faltan experiencias habilitadas de Jericó')
   else:
    check(('id="experiencias-locales"' in body)==(expected>0),'Sección de experiencias vacía o contenido publicado oculto: '+slug)
    if expected:check(body.index('id="atractivos-destino"')<body.index('id="experiencias-locales"')<body.index('id="lugares"'),'Experiencias fuera de orden: '+slug)
  case('LU-'+slug+'-01','Historia visible: '+slug,history)
  case('LU-'+slug+'-02','Directorio y filtros presentes: '+slug,directory)
  case('LU-'+slug+'-03','Experiencias dentro del lugar después de atractivos: '+slug,experiences)
 inventory=ROOT/'.local/preprod/regresion/experience-inventory.json';inventory.write_text(json.dumps(experience_inventory,ensure_ascii=False,indent=2)+'\n')
