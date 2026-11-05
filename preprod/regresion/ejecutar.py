#!/usr/bin/env python3
"""Regresión HTTP/BD exclusivamente local. Código 0 listo, 1 bloqueado, 2 entorno inseguro."""
from pathlib import Path
from html.parser import HTMLParser
import base64,datetime,fcntl,hashlib,html,http.cookiejar,json,os,re,secrets,struct,subprocess,sys,time,urllib.error,urllib.parse,urllib.request,zlib
ROOT=Path(__file__).resolve().parents[2]; BASE='http://127.0.0.1:8088'; RUN='regresion-'+secrets.token_hex(8); EMAIL=RUN+'@pruebas.invalid';PASSWORD=secrets.token_urlsafe(24)+'A9';NEW=secrets.token_urlsafe(24)+'B8';REPORT=ROOT/'.local/preprod/regresion';results=[]
PUBLIC_ONLY='--solo-publico' in sys.argv
if PUBLIC_ONLY:REPORT=REPORT/'flujos-publicos'
if '--solo-panel-lugares' in sys.argv:REPORT=REPORT/'panel-lugares'
class Blocked(Exception):pass
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,files=None):
  assert path.startswith('/') and not path.startswith('//'),'Ruta fuera del entorno'
  headers={'X-EL-Local-Regression':'1'};body=None
  if files:
   boundary='----'+secrets.token_hex(16);parts=[]
   for k,v in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
   for name in files:
    if name=='reference_images[]':
     def chunk(tag,data):return struct.pack('!I',len(data))+tag+data+struct.pack('!I',zlib.crc32(tag+data)&0xffffffff)
     png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!IIBBBBB',320,200,8,2,0,0,0))+chunk(b'IDAT',zlib.compress((b'\0'+b'\x82\xab\xc2'*320)*200))+chunk(b'IEND',b'')
    else:png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="evidencia-ficticia.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+png+b'\r\n')
   body=b''.join(parts)+f'--{boundary}--\r\n'.encode();headers['Content-Type']='multipart/form-data; boundary='+boundary
  elif data is not None:body=urllib.parse.urlencode(data).encode()
  try:
   with self.opener.open(urllib.request.Request(BASE+path,body,headers),timeout=15) as r:return r.status,r.read().decode(errors="replace"),r.url
  except urllib.error.HTTPError as e:
   body=e.read().decode()
   if e.code==429 or (e.code==400 and re.search(r'demasiad[oa]s? (?:intentos|solicitudes)|límite de registros|espera quince minutos',body,re.I)):raise Blocked('Límite antiabuso alcanzado; esperar la ventana sin borrar contadores.')
   return e.code,body,e.url
 def get(self,path):return self.request(path)
 def state(self):return json.loads(self.get('/api/account-session.php')[1])
def csrf(body):
 m=re.search(r'name="csrf"\s+value="([^"]+)"',body);assert m,'Falta token CSRF en formulario';return m[1]
def mailpit_link(email,kind,expected=None):
 """Obtiene exclusivamente el mensaje sintético de esta ejecución desde Mailpit local."""
 subject={'verification':'Verifica tu cuenta de Explorando Lugares','recovery':'Recupera tu acceso a Explorando Lugares'}[kind]
 for _ in range(30):
  try:
   with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/messages',timeout=3) as r:messages=json.load(r).get('messages',[])
   for message in messages:
    if message.get('Subject')!=subject or not any(a.get('Address','').lower()==email.lower() for a in message.get('To',[])):continue
    ident=message['ID'];check(re.fullmatch(r'[A-Za-z0-9_-]+',ident),'Identificador Mailpit inválido')
    with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/message/'+ident,timeout=3) as r:body=json.load(r)
    match=re.search(r'http://127\.0\.0\.1:8088/(?:cuenta/verificar-correo|negocios/cambiar-clave)/\?token=[a-f0-9]{64}',body.get('Text',''))
    check(match and match.group(0) in html.unescape(body.get('HTML','')),'Correo incompleto o enlace ausente en HTML y texto')
    if expected and match.group(0)!=expected:continue
    return match.group(0)
  except (OSError,ValueError):pass
  time.sleep(.25)
 raise Blocked('Mailpit no recibió el mensaje local de esta ejecución.')
def check(value,message):
 if not value:raise AssertionError(message)
def php(code):
 source='''<?php $c=require '/srv/state/private/config.php';if(empty($c['local_test'])||!is_dir('/srv/state'))exit(80);$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'''+code
 proc=subprocess.run(['podman','exec','-i','explorando-preprod','php'],input=source.encode(),capture_output=True,timeout=30)
 if proc.returncode:
  diagnostic=proc.stderr.decode(errors='replace')
  match=re.search(r'SQLSTATE\[([^]]+)\](?:[^\n]*?:\s*(\d+))?',diagnostic)
  raise RuntimeError('Comprobación local falló'+(' SQLSTATE '+match[1]+(' / '+match[2] if match[2] else '') if match else ' (salida '+str(proc.returncode)+')'))
 return proc.stdout.decode()
def query(sql,params=[]):
 payload=base64.b64encode(json.dumps([sql,params]).encode()).decode();return json.loads(php('$v=json_decode(base64_decode("'+payload+'"),true);$q=$p->prepare($v[0]);$q->execute($v[1]);echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));'))
DEPENDENCIES={**{key:['NG-00'] for key in ['NG-06','PG-01','PG-03','PG-04','PG-05','PG-06','AZ-04']},
 **{key:['NG-06'] for key in ['PG-03','PG-04','PG-05']},
 'CU-02':['CU-01'],'CU-03':['CU-01'],'CU-04':['CU-02','CU-03'],'CU-05':['CU-01'],
 'AU-01':['CU-04'],'AU-02':['CU-04'],'AU-03':['AU-01'],'AU-04':['AU-03'],
 'AZ-01':['AU-03'],'AZ-02':['AU-03'],'RC-01':['CU-04'],'RC-02':['CU-01'],
 'AU-05':['RC-01'],'NG-00':['RC-01'],'NG-01':['NG-00'],'NG-02':['NG-00'],
 'NG-03':['NG-00'],'NG-04':['NG-03'],'NG-05':['NG-00'],'SU-01':['NG-00'],
 'CT-03-admin':['CT-03-suggestion','CT-03-petition','CT-03-complaint','CT-03-claim'],
 'CT-03-privacy':['CT-03-suggestion','CT-03-petition','CT-03-complaint','CT-03-claim'],
 'CT-03-consent':['CT-03-claim'],
 'SU-02':['NG-00'],'PB-02':['NG-00'],'AZ-03':['NG-00'],
 **{'AD-'+str(n).zfill(2):['AD-00'] for n in list(range(1,17))+[19]},
 **{'AP-'+str(n).zfill(2):['AP-00'] for n in range(1,15)}}
def case(id,title,fn):
 try:
  unmet=[dep for dep in DEPENDENCIES.get(id,[]) if not any(r['id']==dep and r['status']=='APROBADA' for r in results)]
  if unmet:raise Blocked('Dependencias no aprobadas: '+', '.join(unmet)+'. No se ejecutó este proceso ni se atribuye un fallo nuevo.')
  fn();status='APROBADA';detail='Comprobación ejecutada correctamente.'
 except Blocked as e:status='BLOQUEADA';detail=str(e)
 except Exception as e:status='FALLIDA';detail=str(e) if isinstance(e,(AssertionError,RuntimeError)) else type(e).__name__+': error al ejecutar; revisar entorno.'
 results.append({'id':id,'title':title,'status':status,'detail':detail});print(id,status,title,flush=True)
def pending(id,title,detail):results.append({'id':id,'title':title,'status':'PENDIENTE','detail':detail});print(id,'PENDIENTE',title,flush=True)
def account():
 rows=query('SELECT id,status,email_verified_at FROM accounts WHERE email=?',[EMAIL]);check(len(rows)==1,'No se creó exactamente una cuenta');return rows[0]
def login(client,password=PASSWORD):
 code,page,_=client.get('/negocios/acceso/');check(code==200,'No abre acceso');code,page,url=client.request('/negocios/acceso/',{'csrf':csrf(page),'email':EMAIL,'password':password});check(code==200 and client.state().get('authenticated'),'No se autenticó la cuenta');check('/cuenta/espacio/' not in url,'El acceso todavía obliga a elegir un rol');return page
anon=Client();person=Client();fixtures=[]
from data_guard import capture as capture_persistent,verify as verify_persistent
persistent_guard=None
for attempt in range(3):
 try:
  runtime=json.loads(anon.get('/__preprod-runtime.php')[1]);check(runtime['database']['driver']=='mysql','Se requiere MariaDB/MySQL, no SQLite')
  php('echo "LOCAL";')
  persistent_guard=capture_persistent(query)
  break
 except Exception as error:
  if attempt<2:time.sleep(2);continue  # Read-only probes; no fixtures exist yet.
  detail=str(error) if isinstance(error,RuntimeError) else type(error).__name__
  print('Entorno no comprobado: '+detail+'. No se ejecutó ninguna escritura.');sys.exit(2)
try:
 if '--solo-panel-lugares' not in sys.argv:
  def signup():
   code,page,_=person.get('/negocios/registro/?tipo=traveler');check(code==200,'No abre crear cuenta')
   code,page,_=person.request('/negocios/registro/?tipo=traveler',{'csrf':csrf(page),'residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368','account_type':'traveler','display_name':RUN,'email':EMAIL,'password':PASSWORD,'password_confirm':PASSWORD,'consent':'1'})
   check(code==200,'Falló creación de cuenta personal');row=account();check(row['status']=='pending_activation' and not row['email_verified_at'],'El alta debe quedar pendiente hasta verificar el correo');check(not person.state().get('authenticated'),'El registro autenticó sin confirmar el correo')
  case('CU-01','Crear cuenta personal con nombre, correo y contraseña',signup)
  def verification():
   row=account();messages=json.loads((ROOT/'.local/preprod/private/mailbox.json').read_text()) if (ROOT/'.local/preprod/private/mailbox.json').exists() else []
   check(any(m.get('to')==EMAIL and '/cuenta/verificar-correo/' in m.get('url','') for m in messages),'No llegó un enlace de activación al buzón local')
  case('CU-02','Recibir enlace de verificación de correo al registrarse',verification)
  def activation_guard():
   fresh=Client();_,page,_=fresh.get('/negocios/acceso/');fresh.request('/negocios/acceso/',{'csrf':csrf(page),'email':EMAIL,'password':PASSWORD});check(not fresh.state().get('authenticated'),'El sistema permite iniciar sesión sin haber verificado el correo')
  case('CU-03','Impedir uso de cuenta sin validar el correo',activation_guard)
  def resend_activation():
   c=Client();code,page,_=c.get('/cuenta/verificar-correo/');check(code==200,'No abre solicitud de reenvío')
   mailbox=ROOT/'.local/preprod/private/mailbox.json';before=json.loads(mailbox.read_text());count=sum(m.get('to')==EMAIL and m.get('kind')=='verification' for m in before)
   code,page,_=c.request('/cuenta/verificar-correo/',{'csrf':csrf(page),'email':EMAIL});check(code==200,'No acepta solicitud de reenvío')
   after=json.loads(mailbox.read_text());check(sum(m.get('to')==EMAIL and m.get('kind')=='verification' for m in after)==count+1,'No se generó un nuevo mensaje de activación')
  case('CU-06','Reenviar activación a cuenta pendiente',resend_activation)
  def activate():
   messages=json.loads((ROOT/'.local/preprod/private/mailbox.json').read_text());message=next((m for m in reversed(messages) if m.get('to')==EMAIL and '/cuenta/verificar-correo/' in m.get('url','')),None);check(message,'Falta correo de activación')
   link=urllib.parse.urlsplit(mailpit_link(EMAIL,'verification',message['url']) if os.getenv('EL_TEST_MAILPIT')=='1' else message['url']);check(link.scheme=='http' and link.netloc=='127.0.0.1:8088','Enlace de activación fuera de preprod');token=urllib.parse.parse_qs(link.query).get('token',[''])[0];check(re.fullmatch(r'[a-f0-9]{64}',token),'Token de activación mal formado')
   route=link.path+'?'+urllib.parse.urlencode({'token':token})
   altered=('0' if token[0]!='0' else '1')+token[1:];check(anon.get(link.path+'?token='+altered)[0]==400,'Aceptó enlace alterado');check(account()['status']=='pending_activation','Un enlace alterado activó la cuenta')
   expired=secrets.token_hex(32);query('INSERT INTO account_email_verifications(account_id,token_hash,expires_at) VALUES(?,?,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY))',[account()['id'],hashlib.sha256(expired.encode()).hexdigest()]);check(anon.get(link.path+'?token='+expired)[0]==400,'Aceptó enlace de activación vencido');check(account()['status']=='pending_activation','Un enlace vencido activó la cuenta')
   code,page,_=anon.get(route);check(code==200 and 'Correo verificado' in page,'No confirmó enlace válido');row=account();check(row['status']=='active' and row['email_verified_at'],'No persistió activación y fecha');check(not anon.state().get('authenticated'),'Activación creó sesión sin pedir contraseña');check(anon.get(route)[0]==400,'Se reutilizó enlace de activación')
  case('CU-04','Activar correo por HTTP: válido, vencido, alterado y de un solo uso',activate)
  def badsignup():
   c=Client();_,page,_=c.get('/negocios/registro/?tipo=traveler');code,_,_=c.request('/negocios/registro/?tipo=traveler',{'csrf':csrf(page),'residence_country_code':'CO','residence_region_code':'05','residence_city_code':'05368','account_type':'traveler','display_name':RUN,'email':RUN+'-invalid@pruebas.invalid','password':'123','password_confirm':'otra','consent':'1'});check(code>=400,'Aceptó datos inválidos');check(not query('SELECT id FROM accounts WHERE email=?',[RUN+'-invalid@pruebas.invalid']),'Creó cuenta inválida');check(len(query('SELECT id FROM accounts WHERE email=?',[EMAIL]))==1,'Duplicó cuenta')
  case('CU-05','Contraseñas inválidas o distintas no crean otra cuenta',badsignup)
  case('AU-01','Acceso con correo y contraseña asignada',lambda:login(Client()))
  def wrongpassword():
   c=Client();_,page,_=c.get('/negocios/acceso/');code,_,_=c.request('/negocios/acceso/',{'csrf':csrf(page),'email':EMAIL,'password':'Invalida123456'});check(code>=400 and not c.state().get('authenticated'),'Aceptó contraseña incorrecta')
  case('AU-02','Rechazar contraseña incorrecta',wrongpassword)
  def browsing():
   login(person)
   for route in ['/','/lugares/','/lugares/jerico/','/planea-tu-visita/','/cobertura/colombia/','/negocios-locales/']:
    check(person.get(route)[0]==200,'Falla navegación autenticada: '+route)
   check(person.state().get('name')==RUN,'No conserva nombre/sesión')
  case('AU-03','Navegar libremente autenticado y conservar nombre',browsing)
  def accounttabs():
   code,page,url=person.get('/negocios/portal/');check(code==200 and '/cuenta/espacio/' not in url,'Mi cuenta obliga a elegir un rol');check('account-tabs' in page and 'Viajero' in page and 'Datos personales' in page and RUN in page,'No carga el perfil de viajero')
   check('Registrar o reclamar un negocio' in page,'Falta acceso al registro comercial para cuenta sin negocios')
  case('AU-04','Cuenta de viajero muestra perfil y acceso al registro comercial',accounttabs)
  def privileges():
   code,body,url=person.get('/admin/accounts.php');check(code in (401,403) or ('/negocios/acceso/' in url and 'admin-sidebar' not in body),'Una persona sin rol entra al administrador')
   state=person.state();check(not state.get('administrator'),'Cuenta nueva recibió privilegios')
   check(person.request('/api/account-session.php',{'csrf':state['csrf'],'space':'administrator'})[0]>=400,'Aceptó un rol arbitrario')
  case('AZ-01','No escalar de viajero a administrador manipulando espacio',privileges)
  def csrfguard():
   code,body,url=person.request('/negocios/salir/',{'csrf':'invalido'});check(code in (400,403) or '/negocios/acceso/' in url,'Logout acepta CSRF inválido');check(person.state()['authenticated'],'CSRF inválido cerró sesión')
  case('AZ-02','Rechazar acciones sin CSRF válido',csrfguard)
  def recovery():
   c=Client();_,page,_=c.get('/negocios/recuperar-clave/');code,page,_=c.request('/negocios/recuperar-clave/',{'csrf':csrf(page),'email':EMAIL});check(code==200 and 'recibirás instrucciones para continuar' in page,'No confirma recepción genérica')
   messages=json.loads((ROOT/'.local/preprod/private/mailbox.json').read_text());message=next((m for m in reversed(messages) if m['to']==EMAIL and '/cambiar-clave/' in m.get('url','')),None);check(message,'No llegó correo al buzón local')
   link=urllib.parse.urlsplit(mailpit_link(EMAIL,'recovery') if os.getenv('EL_TEST_MAILPIT')=='1' else message['url']);check(link.netloc=='127.0.0.1:8088','Enlace sale de preprod');path=link.path+'?'+link.query
   code,page,_=c.get(path);check(code==200 and 'Nueva contraseña' in page,'No abre restablecimiento')
   check('Enlace vencido o no válido' in c.get(link.path+'?token='+secrets.token_hex(32))[1],'Acepta token aleatorio')
   token=urllib.parse.parse_qs(link.query)['token'][0];code,page,_=c.request('/negocios/cambiar-clave/',{'csrf':csrf(page),'token':token,'password':NEW,'password_confirm':'Diferente123456'});check(code>=400,'Aceptó contraseñas diferentes')
   code,page,_=c.get(path);code,page,_=c.request('/negocios/cambiar-clave/',{'csrf':csrf(page),'token':token,'password':NEW,'password_confirm':NEW});check(code==200 and 'Contraseña actualizada' in page,'No restauró contraseña')
   check('Enlace vencido o no válido' in c.get(path)[1],'Se puede reutilizar enlace')
   old=Client();_,page,_=old.get('/negocios/acceso/');check(old.request('/negocios/acceso/',{'csrf':csrf(page),'email':EMAIL,'password':PASSWORD})[0]>=400,'La clave anterior sigue funcionando');login(Client(),NEW)
  def expiredreset():
   raw=secrets.token_hex(32);query('INSERT INTO account_password_resets(account_id,token_hash,expires_at) VALUES(?,?,DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY))',[account()['id'],hashlib.sha256(raw.encode()).hexdigest()]);check('Enlace vencido o no válido' in anon.get('/negocios/cambiar-clave/?token='+raw)[1],'Acepta enlace vencido')
  case('RC-02','Rechazar enlace de recuperación vencido',expiredreset)
  case('RC-01','Correo local → restaurar → rechazar discrepancia → clave nueva → enlace de un solo uso',recovery)
  def logout():
   login(person,NEW);state=person.state();check(person.request('/negocios/salir/',{'csrf':state['csrf']})[0]==200,'Falla salida');check(not person.state()['authenticated'],'La sesión sigue activa');check('/negocios/acceso/' in person.get('/negocios/portal/')[2],'Portal privado queda accesible')
  case('AU-05','Cerrar sesión y revocar acceso al portal personal',logout)
  def fixture_businesses():
   d=query('SELECT id FROM destinations WHERE slug=? AND publication_status=?',['jerico','published']);check(d,'Falta Jericó publicado en preprod')
   category=query('SELECT code FROM service_categories WHERE is_active=1 ORDER BY display_order LIMIT 1')[0]['code'];did=d[0]['id'];aid=account()['id'];other=EMAIL.replace('@','-owner@');owner_hash=php('echo password_hash(base64_decode("'+base64.b64encode(NEW.encode()).decode()+'"),PASSWORD_DEFAULT);');query('INSERT INTO accounts(public_id,account_type,display_name,email,status,password_hash,email_verified_at) VALUES(?,"traveler",?,?,"active",?,CURRENT_TIMESTAMP)',[secrets.token_hex(16),RUN+' propietario',other,owner_hash]);owner=query('SELECT id FROM accounts WHERE email=?',[other])[0]['id']
   for suffix,claim in [('libre','NO_RECLAMADO'),('ocupado','RECLAMADO'),('publico','NO_RECLAMADO'),('contratacion','RECLAMADO')]:
    publicid=secrets.token_hex(16);name=RUN+' '+suffix
    query('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,"public_import",?,"PUBLICO",?,"ABIERTO","draft")',[publicid,did,category,name,claim]);bid=query('SELECT id FROM businesses WHERE public_id=?',[publicid])[0]['id'];fixtures.append(bid)
    query('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")',[bid,name]);query('INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(?,"free","pending_publication")',[bid])
    if suffix=='publico':
     query('UPDATE businesses SET publication_status="published" WHERE id=?',[bid]);query('UPDATE business_subscriptions SET plan_code="page",status="active",starts_at=CURRENT_TIMESTAMP,ends_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 1 DAY),reviewed_at=CURRENT_TIMESTAMP WHERE business_id=?',[bid])
     query('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")',[aid,bid])
    if suffix=='ocupado':
     # Owner is the newly created synthetic person. A separate authenticated claimant is tested below.
     query('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")',[owner,bid])
    if suffix=='contratacion':
     query('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")',[aid,bid])
   return did,category
  ctx={}
  def business_setup():
   ctx['destination'],ctx['category']=fixture_businesses();alternate=Client();_,owner_page,_=alternate.get('/negocios/acceso/');alternate.request('/negocios/acceso/',{'csrf':csrf(owner_page),'email':EMAIL.replace('@','-owner@'),'password':NEW});check(alternate.state().get('authenticated'),'No entra propietario sintético alterno');ctx['alternate']=alternate;ctx['alternate_id']=query('SELECT id FROM accounts WHERE email=?',[EMAIL.replace('@','-owner@')])[0]['id'];login(person,NEW);check(person.get('/negocios/portal/?tab=business')[0]==200,'No abre Mis negocios')
  case('NG-00','Preparar negocios sintéticos: libre, asociado y ficha pública con suscripción',business_setup)
  def business_tabs():
   for tab,title in [('personal','Datos personales'),('business','Mis negocios')]:
    code,body,_=person.get('/negocios/portal/?tab='+tab);check(code==200 and title in body and 'Viajero' in body and 'Mis negocios' in body and RUN in body,'No carga la pestaña '+tab+' para la cuenta con negocios')
  case('AU-06','Cuenta con negocios muestra Viajero y Mis negocios',business_tabs)
  def search():
   url='/negocios/registro/?tipo=business&search=1&'+urllib.parse.urlencode({'destination_id':ctx['destination'],'category':ctx['category'],'q':RUN})
   code,body,_=person.get(url);check(code==200 and RUN in body,'La búsqueda no devuelve negocios sintéticos')
  case('NG-01','Buscar negocio por lugar, categoría y nombre',search)
  def application(kind,bid=None,plan='free',frequency='monthly',actor=None):
   client=actor or person
   _,page,_=client.get('/negocios/registro/?tipo=business');fields={'holder_kind':'natural_document','holder_name':RUN,'holder_document_type':'CC','holder_number':'123456789','csrf':csrf(page),'account_type':'business','application_type':kind,'destination_id':ctx['destination'],'category_code':ctx['category'],'relationship_code':'owner','business_name':query('SELECT display_name FROM business_translations WHERE business_id=? AND locale="es"',[bid])[0]['display_name'] if bid else RUN+' nuevo'+(' '+plan if plan!='free' else ''),'business_id':bid or '', 'display_name':RUN,'email':EMAIL,'message':'Solicitud ficticia automatizada','consent':'1','plan_code':plan,'billing_frequency':frequency}
   fields['business_nit']='900123456';fields['responsibility_declared']='1'
   if kind=='new_business' and plan!='free':fields['business_name']+=' '+{'featured':'101','page':'202','page_premium':'303'}[plan]
   if plan!='free':fields.update({'summary':'Negocio ficticio para pruebas locales de registro.','address_text':{'featured':'Calle 10 # 1-10','page':'Carrera 25 # 31-47','page_premium':'Diagonal 42 # 65-23'}[plan],'channels[phone]':'+573001234567'})
   if plan=='page_premium':
    check('name="channels[facebook]"' in page and 'name="channels[linkedin]"' in page,'El formulario Premium no presenta Facebook y LinkedIn')
    fields.update({'channels[facebook]':'https://example.invalid/facebook-registro','channels[linkedin]':'https://example.invalid/linkedin-registro'})
   files=['identity_document','business_registration']
   if plan in ('page','page_premium'):files.append('reference_images[]')
   return client.request('/negocios/registro/?tipo=business',fields,files)
  def newbusiness():
   code,page,url=application('new_business');notice=re.search(r'<[^>]+(?:role="alert"|class="notice")[^>]*>(.*?)</[^>]+>',page,re.S);detail=html.unescape(re.sub('<[^>]+>',' ',notice.group(1))).strip() if notice else '';check(code==200,'No guarda solicitud de negocio nuevo (HTTP '+str(code)+'): '+detail[:200]);apps=query('SELECT id FROM business_account_applications WHERE account_id=? AND application_type="request_new"',[account()['id']]);check(apps,'No persistió solicitud nueva')
  case('NG-02','Crear solicitud de negocio nuevo con la misma cuenta personal',newbusiness)
  def freeclaim():
   code,page,_=application('existing_business',fixtures[0]);check(code==200,'No guarda reclamación de negocio libre');apps=query('SELECT id FROM business_account_applications WHERE account_id=? AND business_id=?',[account()['id'],fixtures[0]]);check(apps,'No persistió reclamación')
  case('NG-03','Guardar reclamación de negocio gratuito sin dueño',freeclaim)
  def freeassignment():
   check(query('SELECT id FROM business_account_memberships WHERE account_id=? AND business_id=? AND status="active"',[account()['id'],fixtures[0]]),'El negocio libre queda pendiente de revisión manual; todavía no existe asignación condicionada a validación automática')
  case('NG-04','Vincular negocio libre tras validación automática',freeassignment)
  def disputedclaim():
   before=query('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"',[fixtures[1]])
   code,page,_=application('existing_business',fixtures[1]);check(code==200,'Rechaza la reclamación en lugar de guardarla para revisión')
   check(query('SELECT id FROM business_account_applications WHERE account_id=? AND business_id=?',[account()['id'],fixtures[1]]),'No guarda reclamación pendiente')
   check(query('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"',[fixtures[1]])==before,'Alteró propietario sin revisión')
  from contacto_operacion import disputed_claim
  case('NG-05','Reclamación controvertida queda en revisión sin cambiar propietario',lambda:disputed_claim(globals()))
  def choices():
   page=person.get('/negocios/registro/?tipo=business')[1];check('name="plan_code"' in page,'Registro no permite elegir suscripción');check('name="billing_frequency"' in page,'Registro no permite elegir frecuencia')
  case('SU-01','Elegir gratuito o suscripción paga al crear el negocio',choices)
  def billing():
   page=person.get('/business/payment.php')[1]
   check(all('value="'+v+'"' in page for v in ['monthly','quarterly','semiannual','annual']),'No ofrece las cuatro frecuencias: mensual, trimestral, semestral y anual')
  case('SU-02','Ofrecer cuatro periodicidades de facturación',billing)
  def approved_prices():
   for plan,monthly in [('featured',25000),('page',40000),('page_premium',60000)]:
    for frequency,multiplier in [('monthly',1),('quarterly',2.7),('semiannual',5.1),('annual',9.6)]:
     rows=query('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?',[plan,frequency]);check(rows and rows[0]['currency']=='COP' and round(float(rows[0]['amount']),2)==round(monthly*multiplier,2),'Tarifa incorrecta '+plan+'/'+frequency)
  case('SU-03','Tarifas vigentes: trimestre 2,7; semestre 5,1; anual 9,6 meses',approved_prices)
  from negocios_publicos import run as run_public_businesses
  run_public_businesses(globals())
  def free_choice():
   apps=query('SELECT business_details_json FROM business_account_applications WHERE account_id=? AND application_type="request_new" ORDER BY id ASC LIMIT 1',[account()['id']]);check(apps,'Falta alta nueva')
   quote=json.loads(apps[0]['business_details_json'])['requested_subscription'];check(quote['plan_code']=='free' and float(quote['quoted_amount'])==0 and quote['billing_frequency'] is None,'Gratis pide cobro o frecuencia')
   check(not query('SELECT id FROM businesses WHERE slug=?',[RUN+' nuevo']),'Se publica/crea negocio sin revisión')
  case('PG-02','Guardar elección gratuita sin exigir pago ni activar publicación',free_choice)
  def offered_catalog():
   code,page,_=person.get('/business/payment.php');check(code==200,'No abre selección de pago');check('value="basic"' not in page,'Básico aún se ofrece');check(all('value="'+v+'"' in page for v in ['featured','page','page_premium']),'Falta nivel de pago vigente')
  case('SV-catalogo','Selección de pago ofrece los tres niveles pagos vigentes',offered_catalog)
  def publicroutes():
   for path in ['/','/lugares/','/lugares/jerico/','/planea-tu-visita/','/etapas/','/negocios-locales/','/negocios-locales/interes/','/negocios-locales/impulsor/','/cobertura/colombia/','/privacidad/']:
    check(anon.get(path)[0]==200,'Página pública no accesible: '+path)
   for prefix in ('','/en','/pt-br'):
    code,_,url=anon.get(prefix+'/negocios-locales/planes/?origen=prueba');check(code==200 and url==BASE+prefix+'/negocios-locales/suscripciones/?origen=prueba','Redirección de suscripciones incorrecta: '+prefix)
  def languages():
   for prefix in ['/en','/pt-br']:
    for route in ['/','/lugares/','/planea-tu-visita/','/etapas/','/negocios-locales/','/privacidad/']:
     check(anon.get(prefix+route)[0]==200,'No carga variante de idioma: '+prefix+route)
  case('PB-04','Conservar secciones públicas en inglés y portugués',languages)
  case('PB-01','Navegación anónima por secciones públicas principales',publicroutes)
  def businesses():
   rows=query('SELECT public_id FROM businesses WHERE id=? AND publication_status="published"',[fixtures[2]]);check(rows,'Sin negocio publicado: no se puede validar ficha pública')
   code,page,_=anon.get('/negocios/'+rows[0]['public_id']+'/');check(code==200 and RUN+' publico' in page,'Ficha de suscripción Página Comercial no carga correctamente; HTTP '+str(code))
  case('PB-02','Consultar ficha publicada sin iniciar sesión',businesses)
  def drafts():
   for bid in fixtures[:2]:
    publicid=query('SELECT public_id FROM businesses WHERE id=?',[bid])[0]['public_id'];code,page,_=anon.get('/negocios/'+publicid+'/');check(code==404 and RUN not in page,'Borrador: se esperaba 404 sin datos; HTTP '+str(code))
  case('AZ-03','No exponer los negocios en borrador',drafts)
  def coverage():
   code,page,_=anon.get('/cobertura/colombia/');check(code==200,'No carga mapa');check(all(x in page for x in ['tab-cobertura','tab-visitantes','tab-participar','country-percent','invite-copy']),'Se perdió pestaña, porcentaje o compartir')
   code,payload,_=anon.get('/api/coverage.php');check(code==200 and isinstance(json.loads(payload),dict),'No responde API de cobertura')
   for prefix,label,asset in [('en','Published','cobertura-meta-en.js'),('pt-br','Publicado','cobertura-meta-pt-br.js')]:
    code,page,_=anon.get('/'+prefix+'/cobertura/colombia/');check(code==200 and 'id="map"' in page,'No carga cobertura en '+prefix)
    code,script,_=anon.get('/assets/cobertura/'+asset);check(code==200 and "published:'"+label+"'" in script,'No carga la interacción traducida en '+prefix)
   code,sitemap,_=anon.get('/sitemap.xml');check(code==200 and all('https://explorandolugares.com/'+prefix+'cobertura/colombia/' in sitemap for prefix in ('','en/','pt-br/')),'Faltan rutas de cobertura en sitemap')
  case('PB-03','Cobertura, Audiencia y Haz parte: documento y API',coverage)
  def lead(kind):
   c=Client();code,body,_=c.get('/api/participation.php');state=json.loads(body);check(state.get('enabled'),'Recepción de solicitudes deshabilitada')
   # Read accepted option from the actual public form, rather than inventing a category.
   page=c.get('/negocios-locales/interes/')[1]
   cats=re.search(r'<select[^>]*name="category"[^>]*>(.*?)</select>',page,re.S);check(cats,'No se encuentra categoría del formulario');vals=re.findall(r'<option value="([^"]+)"',cats[1]);check(vals,'Sin categorías')
   data={'csrf':state['csrf'],'request_key':state['request_key'],'request_type':kind,'name':RUN,'business':RUN,'municipality':'Jericó','category':vals[0],'email':EMAIL,'consent':'1','understanding':'1','description':'Prueba local automatizada, no contactar'}
   code,body,_=c.request('/api/participation.php',data)
   if code==429:raise Blocked('Límite antiabuso local alcanzado por ejecuciones repetidas. No se desactivó; repetir tras la ventana de espera.')
   check(code==200,'No guarda solicitud de '+kind)
   rows=query('SELECT id,interest FROM leads WHERE email=? ORDER BY id DESC',[EMAIL]);check(rows and rows[0]['interest']==('impulsor_local' if kind=='impulsor_local' else 'contacto'),'Tipo de solicitud incorrecto o sin persistencia')
   before=len(rows);c.request('/api/participation.php',data);check(len(query('SELECT id FROM leads WHERE email=?',[EMAIL]))==before,'Reenvío duplica solicitud')
  case('CT-01','Consulta de negocio anónima: guardar y evitar duplicado',lambda:lead('negocio'))
  case('CT-02','Promotor anónimo: guardar separado de consulta comercial',lambda:lead('impulsor_local'))
  if not PUBLIC_ONLY:
   from contacto_operacion import run as run_contact_operation
   run_contact_operation(globals())
 from suscripciones_visuales import run as run_subscription_visuals
 run_subscription_visuals(case,check,anon.get)
 if not PUBLIC_ONLY:
  from administracion_lugares import run as run_extended
  run_extended(globals())
 browser_report=None
 def browser_checks():
  global browser_report
  if '--solo-panel-lugares' in sys.argv:
   profile=json.loads((ROOT/'.local/preprod/test-accounts.json').read_text())['profiles']['negocio'];email,password=profile['email'],profile['password']
  else:
   check(any(r['id']=='NG-00' and r['status']=='APROBADA' for r in results),'Sin cuenta técnica válida para prueba de navegador');email,password=EMAIL,NEW
  report_path=ROOT/'.local/preprod/regresion/browser/results.json'
  if report_path.exists():report_path.unlink()  # Never approve from a previous run's evidence.
  process=subprocess.run(['node',str(ROOT/'preprod/regresion/browser-checks.cjs')],cwd=ROOT,input=json.dumps({'base':BASE,'email':email,'password':password}),text=True,capture_output=True,timeout=240)
  (ROOT/'.local/preprod/regresion/browser-run.log').write_text(process.stdout+process.stderr)
  check(report_path.is_file(),'No se generó evidencia del navegador')
  browser_report=json.loads(report_path.read_text());check(browser_report['base']==BASE,'Evidencia de otro ambiente')
 case('BROWSER','Ejecutar navegador local en escritorio y móvil, sin mensajes ni compras',browser_checks)
 for criterion,title in [('UI-01','Modal, sesión, cuenta, menú y cierre en escritorio/móvil'),('UI-02','Mapa, selección, audiencia, compartir y errores en escritorio/móvil'),('LU-UI','Directorio, filtros, tarjetas y experiencias en tres idiomas'),('SV-operador','Complemento opcional separado y tarifas por período en escritorio/móvil')]:
  def browser_criterion(criterion=criterion):
   check(browser_report is not None,'No hay evidencia actual de navegador');items=[r for r in browser_report['results'] if r['id']==criterion]
   check(len(items)==2 and all(r['passed'] for r in items),'; '.join(r.get('error','Sin evidencia completa') for r in items if not r['passed']) or 'Falta escritorio o móvil')
   check(not browser_report['script_errors'],'Errores JavaScript durante el recorrido')
  case(criterion,title,browser_criterion)
except Exception as e:
 results.append({'id':'ENTORNO','title':'Ejecución completa','status':'FALLIDA','detail':type(e).__name__+': la suite fue interrumpida'})
finally:
 try:
  # El alta vigente crea una ficha privada y una versión editorial asociada.
  # Solo incluir negocios nuevos de esta ejecución, nunca los reclamados.
  created=query('SELECT DISTINCT b.id FROM business_account_applications a JOIN accounts c ON c.id=a.account_id JOIN businesses b ON b.id=a.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE c.email IN (?,?) AND a.application_type="request_new" AND t.display_name LIKE ?',[EMAIL,EMAIL.replace('@','-owner@'),RUN+'%'])
  for item in created:
   if item['id'] not in fixtures:fixtures.append(item['id'])
  paymentFiles=query('SELECT storage_name FROM business_subscription_payment_evidence WHERE account_id IN (SELECT id FROM accounts WHERE email IN (?,?))',[EMAIL,EMAIL.replace('@','-owner@')])
  for r in paymentFiles:
   name=r['storage_name'];check(re.fullmatch(r'[A-Za-z0-9_.-]+',name),'Nombre de comprobante inseguro')
   path=ROOT/'.local/preprod/private/business-payment-evidence'/name
   if path.is_file():path.unlink()
  rows=query('SELECT e.storage_name FROM business_account_application_evidence e JOIN business_account_applications a ON a.id=e.application_id JOIN accounts c ON c.id=a.account_id WHERE c.email IN (?,?)',[EMAIL,EMAIL.replace('@','-owner@')])
  for r in rows:
   # Only exact files referenced by applications created by this run.
   name=r['storage_name'];check(re.fullmatch(r'[A-Za-z0-9_.-]+',name),'Nombre de archivo no seguro')
   path=ROOT/'.local/preprod/private/business-representation-evidence'/name
   if path.is_file():path.unlink()
  # Image rows cascade on account/application deletion; their private files do not.
  # Capture their exact names first, including images copied to our business fixtures.
  imageFiles=query('SELECT i.storage_name FROM business_application_images i JOIN business_account_applications a ON a.id=i.application_id JOIN accounts c ON c.id=a.account_id WHERE c.email IN (?,?)',[EMAIL,EMAIL.replace('@','-owner@')])
  if fixtures:
   imageFiles+=query('SELECT storage_name FROM business_image_references WHERE business_id IN ('+','.join('?' for _ in fixtures)+')',fixtures)
  for r in imageFiles:
   name=r['storage_name'];check(re.fullmatch(r'[A-Za-z0-9_.-]+',name),'Nombre de imagen inseguro')
   path=ROOT/'.local/preprod/private/business-image-references'/name
   check(not path.is_symlink(),'Imagen técnica inesperadamente enlazada')
   if path.is_file():path.unlink()
  # Delete only orders belonging to the two synthetic accounts of this run.
  synthetic=[EMAIL,EMAIL.replace('@','-owner@')]
  query('DELETE e FROM business_payment_order_evidence e JOIN business_payment_orders o ON o.id=e.order_id JOIN accounts a ON a.id=o.account_id WHERE a.email IN (?,?)',synthetic)
  query('DELETE i FROM business_payment_order_items i JOIN business_payment_orders o ON o.id=i.order_id JOIN accounts a ON a.id=o.account_id WHERE a.email IN (?,?)',synthetic)
  query('DELETE o FROM business_payment_orders o JOIN accounts a ON a.id=o.account_id WHERE a.email IN (?,?)',synthetic)
  query('DELETE FROM business_editorial_versions WHERE account_id IN (SELECT id FROM accounts WHERE email IN (?,?))',synthetic)
  query('DELETE FROM leads WHERE email=?',[EMAIL]);query('DELETE FROM accounts WHERE email IN (?,?)',[EMAIL,EMAIL.replace('@','-owner@')])
  for bid in fixtures:
   query('DELETE FROM businesses WHERE id=? AND (slug LIKE ? OR EXISTS (SELECT 1 FROM business_translations WHERE business_id=? AND locale="es" AND display_name LIKE ?))',[bid,RUN+'%',bid,RUN+'%'])
  mailbox=ROOT/'.local/preprod/private/mailbox.json'
  if mailbox.exists():
   with mailbox.open('r+') as f:
    fcntl.flock(f,fcntl.LOCK_EX);messages=json.load(f);f.seek(0);json.dump([m for m in messages if m.get('to')!=EMAIL],f);f.truncate();fcntl.flock(f,fcntl.LOCK_UN)
  check(not query('SELECT id FROM accounts WHERE email IN (?,?)',[EMAIL,EMAIL.replace('@','-owner@')]),'Quedaron cuentas técnicas')
  check(not query('SELECT id FROM leads WHERE email=?',[EMAIL]),'Quedaron solicitudes técnicas')
  if fixtures:check(not query('SELECT id FROM businesses WHERE id IN ('+','.join('?' for _ in fixtures)+')',fixtures),'Quedaron negocios técnicos')
  if mailbox.exists():check(not any(m.get('to')==EMAIL for m in json.loads(mailbox.read_text())),'Quedaron correos técnicos')
  for r in imageFiles:check(not (ROOT/'.local/preprod/private/business-image-references'/r['storage_name']).exists(),'Quedaron imágenes técnicas')
  detail='Cuentas, negocios, solicitudes, imágenes privadas, comprobantes y mensajes del buzón local retirados; cuentas persistentes conservadas.'
  if os.getenv('EL_TEST_MAILPIT')=='1':detail+=' Las copias en Mailpit no se limpian ni verifican en esta ejecución.'
  results.append({'id':'LIMPIEZA','title':'Retirar y verificar ausencia de datos sintéticos de esta ejecución','status':'APROBADA','detail':detail})
 except Exception:results.append({'id':'LIMPIEZA','title':'Limpieza de datos sintéticos','status':'FALLIDA','detail':'No se completó la limpieza. Identificador: '+RUN})
 REPORT.mkdir(parents=True,exist_ok=True)
 def preservation():
  check(persistent_guard is not None,'No hay referencia previa de datos persistentes')
  evidence=verify_persistent(persistent_guard,query);(REPORT/'persistent-data-preservation.json').write_text(json.dumps(evidence,ensure_ascii=False,indent=2)+'\n')
 case('DATOS','Conservar negocios, cuentas, credenciales, suscripciones, fotos y visitas locales anteriores',preservation)
 revision=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip();dirty=bool(subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True))
 fingerprint=hashlib.sha256()
 for f in sorted((ROOT/'.local/preprod/release/public').rglob('*')):
  if f.is_file() and f.suffix in {'.php','.html','.js','.css'}:fingerprint.update(str(f.relative_to(ROOT/'.local/preprod/release')).encode());fingerprint.update(f.read_bytes())
 blocked=any(r['status']!='APROBADA' for r in results) or '--solo-panel-lugares' in sys.argv
 report={'executed_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'base_url':BASE,'revision':revision,'working_tree_dirty':dirty,'public_package_sha256':fingerprint.hexdigest(),'ready':not blocked,'decision':'NO LISTO' if blocked else 'LISTO PARA REVISIÓN HUMANA','selection':'flujos-publicos' if PUBLIC_ONLY else ('panel-lugares' if '--solo-panel-lugares' in sys.argv else 'completa'),'scope':'HTTP y navegador real en escritorio/móvil, MariaDB local y conservación de datos persistentes. Correo sintético local; no certifica entregabilidad externa. No accede a producción, despliega ni realiza pagos reales.','results':results}
 (REPORT/'resultado.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
 table=''.join('<tr><td>'+html.escape(r['id'])+'</td><td>'+html.escape(r['title'])+'</td><td>'+r['status']+'</td><td>'+html.escape(r['detail'])+'</td></tr>' for r in results)
 (REPORT/'resultado.html').write_text('<!doctype html><html lang="es"><meta charset="utf-8"><title>Regresión preprod</title><style>body{font:16px/1.5 system-ui;margin:32px;background:#fffaf4;color:#302d29}td,th{padding:12px;border:1px solid #ccc;text-align:left}table{border-collapse:collapse}h1{color:#9c3e2f}</style><h1>'+report['decision']+'</h1><p>'+html.escape(report['scope'])+'</p><p>Commit: '+revision+'; cambios locales: '+str(dirty)+'. Informe de una ejecución, no autorización de despliegue.</p><table><tr><th>ID</th><th>Criterio</th><th>Resultado</th><th>Detalle</th></tr>'+table+'</table></html>')
 print(report['decision'],REPORT/'resultado.html')
 sys.exit(1 if blocked else 0)
