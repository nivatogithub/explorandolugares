#!/usr/bin/env python3
"""Compare the real commercial registration controller on disposable CLI fixtures; no server."""
from contextlib import closing
from importlib.util import spec_from_file_location,module_from_spec
from pathlib import Path
import argparse,hashlib,json,os,re,shutil,sqlite3,subprocess,tempfile,struct,zlib
ROOT=Path(__file__).resolve().parents[1]
def module(name,file):
    spec=spec_from_file_location(name,ROOT/'scripts'/file);m=module_from_spec(spec);spec.loader.exec_module(m);return m
workspace=module('registration_workspace','check-business-workspace-views.py');profile=workspace.profile;editor=workspace.editor
PHP=profile.PHP.replace('signed-profile-token','signed-registration-token').replace("require $i['package'].'/public/business/dashboard.php';","require $i['package'].'/public/business/register.php';")
PHP=PHP.replace("'HTTP_HOST'=>'127.0.0.1'","'HTTP_HOST'=>'127.0.0.1','HTTP_X_EL_LOCAL_REGRESSION'=>'1'")
PHP=PHP.replace("$_SESSION=['csrf'=>'signed-registration-token'];","$_SESSION=['csrf'=>'signed-registration-token','business_onboarding_draft'=>str_repeat('c',48)];if(isset($i['get']['plan']))$_SESSION['subscription_purchase_intent']=['created_at'=>time(),'plan'=>$i['get']['plan'],'period'=>$i['get']['period']??'monthly'];")
def processing(package):
    source=(package/'public/business/register.php').read_text()
    marker="// A rejected submission must keep the private files visibly recoverable too."
    prefix=source.split(marker,1)[0].replace("require_once __DIR__.'/../_account-page.php';account_page(false);\n",'')
    recovery=source.split(marker,1)[1].split('\n',2)[:2]
    return prefix,recovery

def run(before,after,database,output,allow_query_extraction=False):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(p.is_relative_to(ROOT/'.local') for p in [before,after,database])
    if allow_query_extraction:
        assert processing(before)[1]==processing(after)[1], 'Recovered uploads changed'
        assert '->prepare(' not in (after/'public/business/register.php').read_text()
    else:
        assert processing(before)==processing(after),'Registration processing or recovered uploads changed'
    checks=[];stored_checks=[]
    with tempfile.TemporaryDirectory(prefix='business-registration-',dir=ROOT/'.local') as temporary:
        root=Path(temporary);seed=root/'seed.sqlite';shutil.copy2(database,seed)
        prepared=editor.php(editor.SEED,{'package':str(before),'database':str(seed)})
        owner,admin=prepared['owner'],prepared['admin']
        with closing(sqlite3.connect(seed)) as db:
            place=db.execute('SELECT destination_id FROM businesses WHERE id=?',(prepared['plans']['free']['business'],)).fetchone()[0]
            db.execute('UPDATE accounts SET email_verified_at="2026-01-01 00:00:00",status="active" WHERE id=?',(owner,))
            seed_input={'application_type':'new_business','business_name':'Comercio <seguro> & "privado"','destination_id':str(place),'category_code':'hospedaje','plan_code':'page_premium','billing_frequency':'annual','summary':'Una descripción privada.','business_nit':'900123456','products':{'0':{'title':'Primero','summary':'Descripción 1'},'4':{'title':'Quinto <seguro>','summary':'Descripción 5'}},'channels':{'phone':'+573001234567','whatsapp':'+573001234567','email':'local@example.invalid'},'temporary_uploads':{'evidence':{'business_registration':'1'*48},'editorial':{'cover':'2'*48,'product-4':'3'*48}},'business_phone':{'type':'fixed','area':'601','number':'1234567','same_whatsapp':'1','whatsapp':'3001234567'},'distinct_address':'Calle <privada> 10'}
            for token,account,status in [('a'*48,owner,'editing'),('b'*48,admin,'editing'),('c'*48,owner,'submitted')]:
                db.execute('INSERT INTO business_registration_drafts(id,account_id,payload_json,wizard_step,revision,status) VALUES(?,?,?,5,2,?)',(token,account,json.dumps(seed_input),status))
            for n,kind,slot,mime in [('1','evidence','business_registration','application/pdf'),('2','editorial','cover','image/webp'),('3','editorial','product-4','image/webp')]:
                db.execute('INSERT INTO business_onboarding_uploads(id,draft_token,upload_kind,slot_code,storage_name,original_name,mime_type,file_size,width,height,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,"2035-01-01 00:00:00")',(n*48,'a'*48,kind,slot,n*40+('.pdf' if n=='1' else '.webp'),'Privado <seguro> '+slot,mime,48,None if n=='1' else 640,None if n=='1' else 480))
            db.commit()
        packages=[]
        for label,source in [('before',before),('after',after)]:
            package=root/label;shutil.copytree(source/'app',package/'app');shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
            (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
            (package/'private/sessions').mkdir(parents=True)
            config={'dsn':'sqlite:'+str(package/'private/fixture.sqlite'),'app_key':'registration-regression-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
            (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');packages.append(package)
        def compare(name,query=None,post=None,mode='owner',status=200,variant='',write=None,stored=False):
            results=[]
            for index,package in enumerate(packages):
                fixture=package/'private/fixture.sqlite';shutil.copy2(seed,fixture)
                with closing(sqlite3.connect(fixture)) as db:
                    if variant=='unverified':db.execute('UPDATE accounts SET email_verified_at=NULL WHERE id=?',(owner,))
                    if variant=='expired-upload':db.execute('UPDATE business_onboarding_uploads SET expires_at="2020-01-01 00:00:00" WHERE id=?',('2'*48,))
                    if stored:db.execute('UPDATE destination_translations SET display_name="Nombre" WHERE destination_id=? AND locale="es"',(place,))
                    db.commit();initial=list(db.iterdump());protected={t:list(db.execute('SELECT * FROM '+t+' ORDER BY 1')) for t in ['accounts','businesses','business_subscriptions','business_account_memberships','business_subscription_payment_evidence']}
                if write=='registered':
                    storage=package/'private/business-onboarding-uploads';storage.mkdir(exist_ok=True)
                    def chunk(kind,data):return struct.pack('!I',len(data))+kind+data+struct.pack('!I',zlib.crc32(kind+data)&0xffffffff)
                    image=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!IIBBBBB',640,480,8,2,0,0,0))+chunk(b'IDAT',zlib.compress((b'\0'+b'\x80\x90\xa0'*640)*480))+chunk(b'IEND',b'')
                    proof=b'%PDF-1.4\nSynthetic private registration proof\n%%EOF'
                    for digit,body in [('1',proof),('2',image)]:
                        filename=digit*40+('.pdf' if digit=='1' else '.png');(storage/filename).write_bytes(body)
                        with closing(sqlite3.connect(fixture)) as db:
                            db.execute('UPDATE business_onboarding_uploads SET storage_name=?,mime_type=?,file_size=? WHERE id=?',(filename,'application/pdf' if digit=='1' else 'image/png',len(body),digit*48));db.commit()
                q={'tipo':'business'}|(query or {})
                request={'package':str(package),'fixture':str(fixture),'sessions':str(package/'private/sessions'),'owner':owner,'admin':admin,'mode':mode,'remembered':None,'get':q,'post':post,'route':'/negocios/registro/?tipo=business'}
                result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(request),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
                assert not result.stderr.strip(),(name,result.stderr[:800]);response=json.loads(result.stdout)
                html=response.pop('html');assert response['http']==status,(name,response['http'],html[-1000:])
                if 'business-registration-form' in html:
                    # The unsaved GET draft is intentionally random; normalize only that token.
                    token=re.search(r'name="registration_draft" value="([a-f0-9]{48})"',html)[1];html=html.replace(token,'0'*48)
                    if stored:
                        translated={'en':'Name','pt-br':'Nome'}[q['lang']]
                        if index==0 and not allow_query_extraction:
                            needle='>'+translated+'</option>';assert needle in html,(name,'legacy stored-data translation not reproduced');html=html.replace(needle,'>Nombre</option>')
                        else:assert re.search(r'>\s*Nombre\s*</option>',html),(name,'stored label translated')
                    assert 'name="password"' not in html and 'name="display_name"' not in html
                    assert html.count('name="summary"')==1 and html.count('data-product-slot=')==5
                    assert 'name="promotion_code"' not in html
                if html.startswith('{'):
                    markup=json.loads(html)
                    # Save response contains a timestamp-free revision, identifier and step.
                    if write=='saved':assert markup['ok'] and markup['revision']==1 and markup['step']==3
                else:
                    parser=workspace.WorkspaceMarkup(package/'public');parser.feed(html);markup=(parser.events,parser.resources)
                with closing(sqlite3.connect(fixture)) as db:
                    for table,rows in protected.items():
                        actual=list(db.execute('SELECT * FROM '+table+' ORDER BY 1'))
                        assert actual==rows if write!='registered' or table=='accounts' else actual[:len(rows)]==rows,(name,'unrelated data changed',table)
                    if write=='registered':
                        assert db.execute('SELECT COUNT(*) FROM businesses').fetchone()[0]==len(protected['businesses'])+1
                        row=db.execute('SELECT b.id,b.publication_status,t.display_name,t.summary FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" ORDER BY b.id DESC LIMIT 1').fetchone()
                        assert row[1]=='draft' and row[2]==post['business_name'] and row[3]==post.get('summary',''),(name,row)
                        subscription=db.execute('SELECT plan_code,status,billing_frequency FROM business_subscriptions WHERE business_id=?',(row[0],)).fetchone()
                        assert subscription[0]==post['plan_code'] and subscription[1]==('pending_publication' if post['plan_code']=='free' else 'pending'),(name,subscription)
                        draft=db.execute('SELECT status,business_id FROM business_registration_drafts WHERE id=?',('a'*48,)).fetchone();assert draft==('submitted',row[0])
                        evidence=db.execute('SELECT storage_name FROM business_account_application_evidence ORDER BY id DESC LIMIT 1').fetchone();assert evidence and (package/'private/business-representation-evidence'/evidence[0]).read_bytes()==proof
                        consumed=db.execute('SELECT COUNT(*) FROM business_onboarding_uploads WHERE consumed_at IS NOT NULL').fetchone()[0];assert consumed==(1 if post['plan_code'] in ['free','featured'] else 2),(name,consumed)
                        assert db.execute('SELECT COUNT(*) FROM accounts').fetchone()[0]==len(protected['accounts'])
                        outcome={'business':row[1:],'subscription':subscription,'draft':draft,'consumed_files':consumed}
                    elif write=='saved':
                        row=db.execute('SELECT payload_json,wizard_step,revision FROM business_registration_drafts WHERE id=?',('d'*48,)).fetchone();assert row and row[1:]==(3,1)
                        payload=json.loads(row[0]);assert payload['business_name']==post['business_name'] and 'password' not in payload and 'csrf' not in payload
                    else:assert list(db.iterdump())==initial,(name,'read/rejected request wrote data')
                    if write!='registered':outcome=workspace.stable_database(db)
                results.append((response,markup,outcome))
            if results[0]!=results[1]:
                a,b=results[0][1],results[1][1]
                if isinstance(a,tuple):
                    mismatch=next(((i,x,y) for i,(x,y) in enumerate(zip(a[0],b[0])) if x!=y),('length/resources',len(a[0]),len(b[0])))
                else:mismatch=(a,b)
                raise AssertionError((name,mismatch))
            (stored_checks if stored else checks).append({'case':name,'http':status,'form_resources_access_and_database_outcomes_preserved':True})
        base={'account_type':'business','csrf':'signed-registration-token','registration_draft':'d'*48,'destination_id':str(place),'application_type':'new_business','category_code':'hospedaje','business_name':'Comercio <seguro> & "privado"','plan_code':'free','billing_frequency':'monthly','business_nit':'900123456','relationship_code':'owner','responsibility_declared':'1','consent':'1'}
        for locale in ['es','en','pt-br']:
            q={'lang':locale}
            compare('empty/'+locale,q)
            compare('selected-place/'+locale,q|{'destination_id':str(place)})
            compare('resume-products-files/'+locale,q|{'draft':'a'*48})
            compare('expired-upload/'+locale,q|{'draft':'a'*48},variant='expired-upload')
            compare('foreign-draft/'+locale,q|{'draft':'b'*48},status=404)
            compare('missing-draft/'+locale,q|{'draft':'e'*48},status=404)
            compare('invalid-draft/'+locale,q|{'draft':'invalid'},status=400)
            compare('unverified/'+locale,q,variant='unverified',status=303)
            compare('guest/'+locale,q,mode='guest')
            for mode in ['expired','invalid_proof']:compare(mode+'/'+locale,q,mode=mode,status=303 if mode=='expired' else 200)
            compare('csrf/'+locale,q,base|{'csrf':'invalid'},status=303)
            compare('missing-plan/'+locale,q,base|{'plan_code':''},status=400)
            compare('missing-identifier/'+locale,q,base|{'business_nit':''},status=400)
            compare('invalid-plan/'+locale,q,base|{'plan_code':'forged'},status=400)
            compare('missing-evidence/'+locale,q,base,status=400)
            compare('invalid-phone-form/'+locale,q,base|{'business_phone':'invalid'},status=400)
            compare('invalid-channel-form/'+locale,q,base|{'channels':'invalid'},status=400)
            compare('stale-draft/'+locale,q,base|{'registration_draft':'a'*48,'draft_revision':'1'},status=400)
            compare('foreign-draft-post/'+locale,q,base|{'registration_draft':'b'*48,'draft_revision':'2'},status=400)
            compare('already-submitted/'+locale,q,base|{'registration_draft':'c'*48},status=303)
            for plan in ['free','featured','page','page_premium']:
                compare('preserved-plan-fields/'+plan+'/'+locale,q,base|seed_input|{'account_type':'business','registration_draft':'a'*48,'draft_revision':'1','plan_code':plan},status=400)
                compare('purchase-plan/'+plan+'/'+locale,q|{'plan':plan,'period':'annual','destination_id':str(place)})
            save=base|{'draft_action':'save','draft_revision':'0','wizard_step':'3','password':'DO-NOT-STORE'}
            compare('saved-draft/'+locale,q,save,write='saved')
            compare('stale-save/'+locale,q,save|{'registration_draft':'a'*48},status=409)
            compare('foreign-save/'+locale,q,save|{'registration_draft':'b'*48,'draft_revision':'2'},status=409)
            compare('csrf-save/'+locale,q,save|{'csrf':'invalid'},status=303)
            for category in ['__categories__','__experiences__','hospedaje','unknown']:
                compare('search/'+category+'/'+locale,q|{'search':'1','destination_id':str(place),'category':category,'q':'Hotel'})
        for plan in ['free','featured','page','page_premium']:
            final=base|{'registration_draft':'a'*48,'draft_revision':'2','plan_code':plan,'billing_frequency':'annual','temporary_uploads':{'evidence':{'business_registration':'1'*48},'editorial':{'cover':'2'*48}},'business_name':'Nuevo comercio '+plan}
            if plan!='free':final|={'summary':'Presentación de prueba','address_text':'Calle de prueba 10','latitude':'5.791','longitude':'-75.786','location_confirmed':'1','location_map_url':'https://www.google.com/maps/search/?api=1&query=5.791,-75.786','business_phone':{'type':'mobile','number':'3001234567','area':'604'}}
            compare('private-registration/'+plan,post=final,status=303,write='registered')
        for locale in ['en','pt-br']:compare('stored-place-label/'+locale,{'lang':locale,'destination_id':str(place)},stored=True)
        output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'isolated_cli_sqlite_fixtures':True,'native_mariadb_verified_separately':not allow_query_extraction,'real_mail_sent':False,'controller_processing_identical':not allow_query_extraction,'checks':checks,'stored_data_checks':stored_checks},indent=2)+'\n')
    print(f'OK: {len(checks)} commercial registration comparisons and {len(stored_checks)} stored-data regressions; forms, plans, files, drafts, searches and access preserved.')
if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    for key in ['before','after','database','output']:p.add_argument('--'+key,type=Path,required=True)
    p.add_argument('--allow-query-extraction',action='store_true')
    run(**vars(p.parse_args()))
