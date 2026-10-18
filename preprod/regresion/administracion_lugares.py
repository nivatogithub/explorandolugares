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
 expected=[('index.php?tab=solicitudes','Solicitudes de participación'),('negocios.php','Negocios'),('contactos-negocios.php','Canales y responsable'),('actualizar-negocios.php','Exportar / actualizar'),('campanas.php','Campañas por correo'),('reclamaciones.php','Reclamos de negocio'),('solicitudes-cuenta.php','Solicitudes de cuenta'),('usuarios.php','Cuentas'),('suscripciones.php','Suscripciones'),('destinos.php','Destinos'),('inversionistas.php','Inteligencia e inversión'),('costos.php','Costos y cancelaciones'),('index.php?tab=sistema','Estado del sistema'),('base-datos.php','Base de datos')]
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
  page=cache.get('negocios.php','');check(page,'Falta respuesta del panel de negocios')
  sidebar=re.search(r'<aside\b.*?</aside>',page,re.S);check(sidebar,'No existe navegación administrativa');parser=Links();parser.feed(sidebar[0]);actual={x['href'] for x in parser.items}
  for route,label in expected:check(route in actual,'Enlace perdido del menú: '+label)
  for item in parser.items:
   href=item['href']
   if not href or href.startswith(('#','http','mailto:')):continue
   url=urllib.parse.urlsplit(urllib.parse.urljoin(BASE+'/admin/negocios.php',href));check(url.netloc=='127.0.0.1:8088','Menú apunta fuera de preprod');code,page,_=admin.get(url.path+('?' +url.query if url.query else ''));check(code==200,'Otra opción del menú falla: '+item['text'].strip())
 case('AD-15','Ninguna opción del menú desaparece; probar también enlaces adicionales',nav_integrity)
 def forbidden(route):
  for label,c in [('anónimo',anon),('persona sin rol',person)]:
   code,page,url=c.get('/admin/'+route)
   check(code in [401,403] or ('admin-sidebar' not in page and ('Inicia sesión' in page or '/negocios/acceso/' in url)),'Acceso administrativo expuesto a '+label+': '+route)
 for n,(route,label) in enumerate(expected,1):case('AP-'+str(n).zfill(2),'Denegar '+label+' a visitantes y cuentas sin rol',lambda route=route:forbidden(route))
 def extra_pages():
  # Document/resource endpoints need real IDs and their own authorization assertions; do not count missing ID as success.
  for path in ['importar-negocios.php','trasladar-negocios.php','medicion.php','reportes.php','solicitudes-publicas.php','configuracion.php','medios-pago.php']:
   code,page,_=admin.get('/admin/'+path);check(code==200 and re.search(r'<h1\b',page) and (path=='reportes.php' or '<form' in page or path=='solicitudes-publicas.php') and not re.search(r'(Fatal error|SQLSTATE\[)',page),'No abre herramienta complementaria '+path+'; HTTP '+str(code));forbidden(path)
 case('AD-16','Acceso a importación, traslado de negocios y medición',extra_pages)
 if any('Próximamente' in p and 'Reportes' in p for p in cache.values()):pending('AD-17','Reportes administrativos funcionales','El menú todavía anuncia Próximamente. No se contabiliza como módulo disponible.')
 def isolated_operations():
  import subprocess,sys
  command=[sys.executable,str(ROOT/'backend/tests/admin-pages.integration.py')]
  process=subprocess.run(command,cwd=ROOT,capture_output=True,text=True,timeout=60)
  log=ROOT/'.local/preprod/regresion/admin-operaciones.log';log.parent.mkdir(parents=True,exist_ok=True);log.write_text(process.stdout+process.stderr)
  check(process.returncode==0,'Regresión HTTP administrativa aislada falló; ver admin-operaciones.log')
 case('AD-18','Operaciones administrativas y documentos privados en base aislada',isolated_operations)
 def adminlogout():
  state=admin.state();admin.request('/negocios/salir/',{'csrf':state['csrf']});check(not admin.state()['authenticated'],'No cerró sesión administradora');code,page,_=admin.get('/admin/usuarios.php');check(code==403,'Cerrar sesión no revoca acceso administrativo')
 case('AD-19','Salida del administrador revoca acceso al panel',adminlogout)
 published=query('SELECT slug FROM destinations WHERE publication_status="published" ORDER BY slug');check(published,'No hay lugares publicados para recorrer')
 def listing():
  code,page,_=anon.get('/destinos/');check(code==200,'No abre listado de lugares')
  parser=Links();parser.feed(page)
  paths={urllib.parse.urlsplit(urllib.parse.urljoin(BASE+'/destinos/',item['href'])).path for item in parser.items}
  for row in published:check(any(path in paths for path in ['/destinos/'+row['slug']+'/', '/destinos/'+row['slug']+'/index.html']),'Lugar publicado sin entrada en listado: '+row['slug'])
 case('LU-01','Listado público permite encontrar todos los lugares publicados',listing)
 for row in published:
  slug=row['slug'];page_cache={}
  def page(slug=slug,page_cache=page_cache):
   if not page_cache:
    code,body,_=anon.get('/destinos/'+slug+'/');check(code==200,'Lugar publicado devuelve HTTP '+str(code));page_cache['html']=body
   return page_cache['html']
  def history(page=page):
   body=page();m=re.search(r'<section\b[^>]*id="descubre"[^>]*>(.*?)</section>',body,re.S);check(m,'Falta sección de historia');text=html.unescape(re.sub('<[^>]+>',' ',m[1]));check(len(text.strip())>100,'Historia ausente o insuficiente');check('href="#descubre"' in body,'No hay acceso interno a historia')
  def directory(page=page):
   body=page();check(all(x in body for x in ['id="lugares"','id="catalogo-local"','id="directory-panel"']),'Falta directorio de comercios');check('href="#lugares"' in body,'Directorio sin enlace interno');check('directory-tab-todos' in body,'Falta filtro de todos los comercios')
  def experiences(page=page,slug=slug):
   body=page()
   if slug=='jerico':
    check('id="experiencias-locales"' in body,'No aparecen experiencias de Jericó');check(body.index('id="atractivos-destino"')<body.index('id="experiencias-locales"')<body.index('id="lugares"'),'Experiencias fuera de su posición después de atractivos');check(body.count('destination-experience-card')>=3,'Faltan experiencias habilitadas de Jericó')
   else:
    if 'id="experiencias-locales"' not in body:raise env['Blocked']('Lugar sin sección de experiencias: revisar inventario editorial; no inventar contenido para aprobar.')
  case('LU-'+slug+'-01','Historia y acceso interno: '+slug,history)
  case('LU-'+slug+'-02','Directorio y filtros presentes: '+slug,directory)
  case('LU-'+slug+'-03','Experiencias dentro del lugar después de atractivos: '+slug,experiences)
 pending('LU-UI','Interacción visual del directorio y experiencias','Comprobar filtros, tarjetas y enlaces en navegador: el HTML presente no demuestra ejecución de JavaScript. Mantener pendiente hasta automatizar ese recorrido.')
