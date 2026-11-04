#!/usr/bin/env python3
"""Exercise contact locale and data preservation with disposable fixture data."""
from pathlib import Path
from html.parser import HTMLParser
import json, os, shutil, subprocess, sys, tempfile
ROOT=Path(__file__).resolve().parents[1]
package=Path(sys.argv[1]).resolve()
assert package.is_relative_to(ROOT/'.local') and (package/'private/presentation.sqlite').is_file()
php=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']); session_id(bin2hex(random_bytes(16))); session_start();
$_SESSION=['csrf'=>'contact-test-token'];if($i['receipt'])$_SESSION['public_request_receipt']='SYNTHETIC-REFERENCE';
$_GET=['lang'=>$i['locale']];$_POST=$i['post']??[];$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/contact.php?lang='.$i['locale'],'HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['status'=>http_response_code()?:200,'html'=>ob_get_clean(),'receipt'=>!empty($_SESSION['public_request_receipt'])],JSON_THROW_ON_ERROR);});require $i['package'].'/public/contact.php';
'''
class Page(HTMLParser):
 def __init__(self):super().__init__();self.links=[];self.parts=[];self.in_main=False;self.title=False;self.title_text='';self.textarea=False;self.message=''
 def handle_starttag(self,tag,attrs):
  attrs=dict(attrs)
  if tag=='main':self.in_main=True
  if tag=='title':self.title=True
  if tag=='textarea':self.textarea=True
  if tag=='a' and self.in_main:self.links.append(attrs.get('href',''))
 def handle_endtag(self,tag):
  if tag=='main':self.in_main=False
  if tag=='title':self.title=False
  if tag=='textarea':self.textarea=False
 def handle_data(self,data):
  if self.title:self.title_text+=data
  if self.textarea:self.message+=data
  if self.in_main:self.parts.append(data)
with tempfile.TemporaryDirectory(prefix='contact-language-',dir=ROOT/'.local') as tmp:
 root=Path(tmp);shutil.copytree(package/'app',root/'app');shutil.copytree(package/'public',root/'public',ignore=shutil.ignore_patterns('assets'));(root/'public/assets').symlink_to(package/'public/assets',target_is_directory=True);(root/'private/sessions').mkdir(parents=True);shutil.copy2(package/'private/presentation.sqlite',root/'private/fixture.sqlite')
 config={'dsn':'sqlite:'+str(root/'private/fixture.sqlite'),'app_key':'isolated-contact-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
 (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
 for locale in ('es','en','pt-br'):
  for case in ('get','receipt','invalid','submitted'):
   shutil.copy2(package/'private/presentation.sqlite',root/'private/fixture.sqlite')
   post=None
   if case in ('invalid','submitted'):post={'csrf':'contact-test-token','request_type':'invalid' if case=='invalid' else 'suggestion','name':'Persona Ejemplo','email':'synthetic@example.invalid','message':'Contacto y ayuda <Test & value>','consent':'1'}
   payload={'package':str(root),'sessions':str(root/'private/sessions'),'locale':locale,'post':post,'receipt':case=='receipt'}
   result=subprocess.run(['php','-d','display_errors=stderr','-r',php],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
   assert not result.stderr.strip(),result.stderr
   response=json.loads(result.stdout);assert response['status']==({'get':200,'receipt':200,'invalid':400,'submitted':303}[case]),(locale,case,response['status'])
   if case=='submitted':assert response['receipt'];continue
   page=Page();page.feed(response['html']);prefix='' if locale=='es' else '/'+locale
   for path in ('/','/negocios-locales/interes/','/negocios-locales/impulsor/','/privacidad/'):
    assert prefix+path in page.links,(locale,path,page.links)
   assert page.title_text.startswith({'es':'Contacto y ayuda','en':'Contact and help','pt-br':'Contato e ajuda'}[locale]),(locale,page.title_text)
   if case=='invalid':assert page.message=='Contacto y ayuda <Test & value>',(locale,page.message)
   if case=='receipt':
    text=' '.join(page.parts);assert 'SYNTHETIC-REFERENCE' in text
    assert {'es':'Conserva este número','en':'Keep this number','pt-br':'Guarde este número'}[locale] in text,(locale,text)
print('OK: 12 contact controller cases; locale links, title, receipt, escaped input and synthetic submission.')
