"""Current Apache/MariaDB admin operations; only owned synthetic rows are mutated."""
import json,secrets,hashlib,urllib.request,urllib.error,urllib.parse

def run(env,admin):
 query,check,csrf=env['query'],env['check'],env['csrf'];guest=env['Client']();root=env['ROOT']
 token='regresion-admin-'+secrets.token_hex(8);uuid=secrets.token_hex(16);bid=lead=None;checks=[]
 def assert_(value,label):check(value,label);checks.append(label)
 def denied(response):
  return response[0] in [401,403] or (response[0]==200 and '/negocios/acceso/' in response[2] and 'name="password"' in response[1])
 try:
  did=query('SELECT id FROM destinations WHERE slug="jerico"')[0]['id'];category=query('SELECT code FROM service_categories WHERE is_active=1 ORDER BY display_order LIMIT 1')[0]['code']
  query('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status,latitude,longitude) VALUES(?,?,?,"public_import",?,"PUBLICO","NO_RECLAMADO","ABIERTO","draft",5.791,-75.786)',[uuid,did,category,token]);bid=query('SELECT id FROM businesses WHERE public_id=?',[uuid])[0]['id']
  for locale in ['es','en','pt-BR']:query('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,"","")',[bid,locale,token])
  query('INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(?,"free","pending_publication")',[bid])
  path='/admin/businesses.php?id='+str(bid);code,body,_=admin.get(path);assert_(code==200 and 'business-form' in body,'Editor actual disponible')
  post={'csrf':csrf(body),'action':'save','id':bid,'destination_id':did,'category_code':category,'plan_code':'free','latitude':'5.791','longitude':'-75.786','translations[es][display_name]':token+' editado'}
  anonymous_response=guest.request(path,post);assert_(denied(anonymous_response),'Anónimo no puede guardar')
  bad=dict(post,csrf='invalid')
  try:
   response=admin.opener.open(urllib.request.Request(env['BASE']+path,data=urllib.parse.urlencode(bad).encode(),headers={'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'}),timeout=15)
   status=response.status;payload=json.loads(response.read())
  except urllib.error.HTTPError as error:status=error.code;payload=json.loads(error.read())
  assert_(status==401 and payload.get('error')=='authentication_required','CSRF inválido rechaza la operación y solicita acceso')
  assert_(query('SELECT display_name FROM business_translations WHERE business_id=? AND locale="es"',[bid])[0]['display_name']==token,'Rechazos no modifican nombre')
  assert_(admin.state().get('administrator'),'El rechazo del formulario conserva la cuenta y sus permisos')
  _,body,_=admin.get(path);post['csrf']=csrf(body)
  code,body,_=admin.request(path,post);assert_(code==200,'Guardar edición administrativa')
  row=query('SELECT b.id,b.public_id,b.publication_status,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.id=?',[bid])[0]
  assert_(row['display_name']==token+' editado' and row['public_id']==uuid and row['publication_status']=='draft','Edición conserva identidad y no publica')
  assert_(guest.get('/negocios/'+uuid+'/')[0]==404,'Borrador permanece privado')
  code,body,_=admin.get('/admin/businesses.php?tab=crear');before=query('SELECT COUNT(*) n FROM businesses')[0]['n'];code,_,_=admin.request('/admin/businesses.php',dict(post,id=0,csrf=csrf(body)))
  assert_(code==403 and query('SELECT COUNT(*) n FROM businesses')[0]['n']==before,'Alta manual prohibida no crea fichas')
  email=token+'@pruebas.invalid';query('INSERT INTO leads(request_key,name,business,municipality,category,has_brand,description,interest,consent_version,email) VALUES(?,?,?,?,?,"no",?,"negocio","test",?)',[token,token,token,'Jericó',category,'<script>owned-fixture</script>',email]);lead=query('SELECT id FROM leads WHERE request_key=?',[token])[0]['id']
  detail='/admin/request-details.php?type=lead&id='+str(lead);code,body,_=admin.get(detail)
  assert_(code==200 and '&lt;script&gt;' in body and '<script>owned-fixture</script>' not in body,'Detalle privado escapa contenido')
  assert_(denied(guest.get(detail)),'Detalle privado denegado al anónimo')
  memberships=query('SELECT COUNT(*) n FROM business_account_memberships WHERE business_id=?',[bid])[0]['n']
  for _ in range(2):
   code,body,_=admin.get(detail);code,_,_=admin.request(detail,{'csrf':csrf(body),'action':'link','business_id':bid,'confirm':'1'});assert_(code==200,'Guardar vínculo informativo')
  assert_(query('SELECT COUNT(*) n FROM lead_business_links WHERE lead_id=? AND business_id=?',[lead,bid])[0]['n']==1,'Repetir vínculo no duplica')
  assert_(query('SELECT COUNT(*) n FROM business_account_memberships WHERE business_id=?',[bid])[0]['n']==memberships,'Vínculo no concede representación')
  assert_(query('SELECT publication_status FROM businesses WHERE id=?',[bid])[0]['publication_status']=='draft','Vínculo no publica')
  # A synthetic paid history must prevent administrative deletion, even if Free is selected.
  query('INSERT INTO business_subscriptions(business_id,plan_code,status,quoted_amount,currency) VALUES(?,"page","expired",40000,"COP")',[bid])
  code,body,_=admin.get(path);code,_,_=admin.request(path,{'csrf':csrf(body),'action':'delete','id':bid,'confirm_delete':'1'})
  assert_(code==400 and bool(query('SELECT id FROM businesses WHERE id=? AND public_id=?',[bid,uuid])),'Historial pago impide borrado')
  # Inspect only proofs belonging to this regression's temporary account.
  docs=query('SELECT e.id,e.storage_name,e.file_size FROM business_subscription_payment_evidence e JOIN accounts a ON a.id=e.account_id WHERE a.email=?',[env['EMAIL']])
  assert_(bool(docs),'Existen comprobantes sintéticos de los recorridos de pago')
  for doc in docs:
   url='/admin/payment-evidence.php?id='+str(doc['id'])
   with admin.opener.open(env['BASE']+url,timeout=15) as response:code=response.status;content=response.read()
   expected=root/'.local/preprod/private/business-payment-evidence'/doc['storage_name']
   assert_(code==200 and len(content)==int(doc['file_size']) and hashlib.sha256(content).digest()==hashlib.sha256(expected.read_bytes()).digest(),'Administrador descarga comprobante íntegro')
   assert_(denied(guest.get(url)),'Comprobante denegado al anónimo')
  report={'passed':True,'database':'local MariaDB','http':'existing preprod Apache','checks':checks,'production_accessed':False}
 finally:
  if lead:query('DELETE FROM leads WHERE id=? AND request_key=?',[lead,token])
  if bid:query('DELETE FROM businesses WHERE id=? AND public_id=? AND slug=?',[bid,uuid,token])
  check(not query('SELECT id FROM businesses WHERE public_id=?',[uuid]),'Limpieza del negocio técnico incompleta')
 if 'report' in locals():(root/'.local/preprod/regresion/admin-operaciones.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
