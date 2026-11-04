#!/usr/bin/env python3
"""Compare workspace rendering and owner actions using isolated CLI copies; no extra server."""
from contextlib import closing
from importlib.util import spec_from_file_location,module_from_spec
from pathlib import Path
import argparse,hashlib,json,os,shutil,sqlite3,subprocess,tempfile
ROOT=Path(__file__).resolve().parents[1]

def module(name,file):
    spec=spec_from_file_location(name,ROOT/'scripts'/file);value=module_from_spec(spec);spec.loader.exec_module(value);return value
editor=module('editor_layout','check-editorial-layout.py');profile=module('profile_layout','check-account-profile-views.py')
PHP=profile.PHP.replace('signed-profile-token','signed-workspace-token').replace("$i['mode']==='admin'?$i['admin']:$i['owner']","in_array($i['mode'],['admin','intruder'],true)?$i['admin']:$i['owner']")

class WorkspaceMarkup(profile.ProfileMarkup):
    def __init__(self,public):super().__init__(public);self.svg_depth=0
    def handle_starttag(self,tag,attrs):
        if tag=='svg':self.svg_depth+=1
        super().handle_starttag(tag,attrs)
    def handle_endtag(self,tag):
        super().handle_endtag(tag)
        if tag=='svg':self.svg_depth-=1
    def handle_startendtag(self,tag,attrs):
        self.handle_starttag(tag,attrs)
        if self.svg_depth:self.handle_endtag(tag)

def processing(package):
    source=(package/'public/business/dashboard.php').read_text()
    markers=["require_once dirname(__DIR__).'/_account-page.php';","require_once dirname(__DIR__).'/_portal-shell.php';"]
    source=source[:min(source.index(m) for m in markers if m in source)]
    for item in ["require_once dirname(__DIR__).'/admin/_asset.php';\n","require_once __DIR__.'/_workspace-icon.php';\n"]:source=source.replace(item,'')
    return source

def stable_database(db):
    result={}
    for (table,) in db.execute('SELECT name FROM sqlite_master WHERE type="table" ORDER BY name').fetchall():
        columns=[r[1] for r in db.execute('PRAGMA table_info("'+table+'")')]
        rows=[]
        for row in db.execute('SELECT * FROM "'+table+'"'):
            rows.append([('set' if value is not None else None) if column.endswith('_at') else value for column,value in zip(columns,row)])
        result[table]=sorted(rows,key=repr)
    return result

def run(before,after,database,output):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(path.is_relative_to(ROOT/'.local') for path in [before,after,database])
    assert processing(before)==processing(after),'Account dispatch, authorization or owner action processing changed'
    checks=[];stored_checks=[]
    with tempfile.TemporaryDirectory(prefix='workspace-layout-',dir=ROOT/'.local') as temporary:
        root=Path(temporary);seed=root/'seed.sqlite';shutil.copy2(database,seed)
        prepared=editor.php(editor.SEED,{'package':str(before),'database':str(seed)})
        owner,admin=prepared['owner'],prepared['admin'];plans=prepared['plans']
        with closing(sqlite3.connect(seed)) as db:
            place=db.execute('SELECT destination_id FROM businesses WHERE id=?',(plans['free']['business'],)).fetchone()[0]
            for index,(plan,item) in enumerate(plans.items()):
                public_id=f'f00d0000-0000-4000-8000-{index+1:012d}';item['public_id']=public_id
                db.execute('UPDATE businesses SET public_id=? WHERE id=?',(public_id,item['business']))
                sub=db.execute('SELECT id FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1',(item['business'],)).fetchone()[0]
                db.execute('UPDATE business_subscriptions SET billing_frequency=?,quoted_amount=?,currency="COP" WHERE id=?',('' if plan=='free' else 'monthly',0 if plan=='free' else 119900,sub))
                db.execute('INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,status,customer_message,submitted_at,transaction_date,paid_amount,reviewed_at) VALUES(?,?,?,?,?,?,?, ?,"2026-01-01 00:00:00","2026-01-01",119900,?)',(sub,owner,'workspace-'+plan+'.pdf','proof.pdf','application/pdf',100,['pending','approved','clarification_requested','rejected'][index],'Respuesta <privada> & "segura"',None if index==0 else '2026-01-02 00:00:00'))
            db.execute('INSERT INTO business_registration_drafts(id,account_id,payload_json,wizard_step) VALUES(?,?,?,3)',('a'*48,owner,json.dumps({'business_name':'Borrador <privado>','destination_id':str(place),'plan_code':'page_premium'})))
            db.execute('INSERT INTO business_account_applications(account_id,application_type,destination_id,category_code,business_name,relationship_code,consent_version,status) VALUES(?,"request_new",?,"hospedaje","Solicitud privada","owner","test","needs_selection")',(owner,place))
            db.commit()
        packages=[]
        for label,source in [('before',before),('after',after)]:
            package=root/label;shutil.copytree(source/'app',package/'app');shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
            (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
            (package/'private/sessions').mkdir(parents=True)
            config={'dsn':'sqlite:'+str(package/'private/fixture.sqlite'),'app_key':'workspace-regression-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
            (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');packages.append(package)
        def compare(name,query=None,post=None,mode='owner',state='draft',variant='',status=200,write=None,stored=False,omit_tab=False):
            results=[]
            for index,package in enumerate(packages):
                fixture=package/'private/fixture.sqlite';shutil.copy2(seed,fixture)
                with closing(sqlite3.connect(fixture)) as db:
                    db.execute('UPDATE business_editorial_versions SET status=?',(state,))
                    if variant in ['unpublished','payment','clarification','validation','editorial']:db.execute('UPDATE businesses SET publication_status="draft"')
                    if variant in ['payment','clarification','validation','editorial']:
                        db.execute('DELETE FROM business_editorial_images');db.execute('DELETE FROM business_editorial_events');db.execute('DELETE FROM business_editorial_versions')
                        db.execute('UPDATE business_subscriptions SET status="pending"')
                        if variant=='payment':db.execute('DELETE FROM business_subscription_payment_evidence')
                        else:db.execute('UPDATE business_subscription_payment_evidence SET status=?',({'clarification':'clarification_requested','validation':'pending','editorial':'approved'}[variant],))
                    if variant=='expired':db.execute('UPDATE business_subscriptions SET ends_at="2020-01-01 00:00:00"')
                    if variant=='private-place':db.execute('UPDATE destinations SET publication_status="draft" WHERE id=?',(place,))
                    if variant=='empty':
                        for table in ['business_account_memberships','business_account_applications','business_claim_requests','business_registration_drafts','business_subscription_payment_evidence']:db.execute('DELETE FROM '+table+' WHERE account_id=?',(owner,))
                    if mode=='disabled':db.execute('UPDATE accounts SET status="disabled" WHERE id=?',(owner,))
                    if write=='published':db.execute('INSERT INTO business_editorial_events(version_id,actor,action) VALUES(?,"admin:fixture","quality_checked")',(plans['free']['version'],))
                    if stored:
                        db.execute('UPDATE accounts SET display_name="Nombre" WHERE id=?',(owner,));db.execute('UPDATE business_translations SET display_name="Nombre" WHERE business_id=?',(plans['free']['business'],))
                        db.execute('UPDATE business_subscription_payment_evidence SET customer_message="Tu cuenta" WHERE subscription_id=(SELECT id FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1)',(plans['free']['business'],))
                    db.commit();initial=list(db.iterdump());identities={table:list(db.execute('SELECT id,public_id FROM '+table+' ORDER BY id')) for table in ['accounts','businesses']}
                    protected={table:list(db.execute('SELECT * FROM '+table+' ORDER BY 1,2')) for table in ['business_account_memberships','business_subscriptions','business_subscription_payment_evidence']}
                request={'package':str(package),'fixture':str(fixture),'sessions':str(package/'private/sessions'),'owner':owner,'admin':admin,'mode':mode,'remembered':None,'get':dict({} if omit_tab else {'tab':'business'},**(query or {})),'post':post,'route':'/negocios/portal/?tab=business'}
                result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(request),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
                assert not result.stderr.strip(),(name,result.stderr[:800]);response=json.loads(result.stdout);assert response['http']==status,(name,response['http'],response['html'].split('role="alert">')[-1].split('</p>')[0][:400])
                html=response.pop('html')
                if stored:
                    language=query['lang'];translation={'en':('Name','Your account'),'pt-br':('Nome','Sua conta')}[language]
                    if index==0 and '_account-page.php' in (before/'public/business/dashboard.php').read_text():
                        for old,new in [('<p>'+translation[0]+'</p>','<p>Nombre</p>'),('title="Nombre">'+translation[0]+'</strong>','title="Nombre">Nombre</strong>'),('<td>'+translation[0]+'</td>','<td>Nombre</td>'),('<p class="business-row-message">'+translation[1]+'</p>','<p class="business-row-message">Tu cuenta</p>')]:
                            assert old in html,(name,'old data translation not reproduced',old);html=html.replace(old,new)
                    else:assert '<p>Nombre</p>' in html and 'title="Nombre">Nombre</strong>' in html and '<p class="business-row-message">Tu cuenta</p>' in html
                if status==200 and mode=='owner' and not stored and variant=='':
                    for plan in ['page','page_premium']:assert 'data-business-qr-url="https://explorandolugares.com/negocios/'+plans[plan]['public_id']+'/' in html,(name,'eligible public page QR missing')
                if variant in ['unpublished','expired','private-place'] and status==200:
                    for plan in ['page','page_premium']:assert 'data-business-qr-url="https://explorandolugares.com/negocios/'+plans[plan]['public_id']+'/' not in html,(name,'ineligible QR exposed')
                parsed=WorkspaceMarkup(package/'public');parsed.feed(html)
                with closing(sqlite3.connect(fixture)) as db:
                    for table,rows in identities.items():assert list(db.execute('SELECT id,public_id FROM '+table+' ORDER BY id'))==rows,(name,'identities changed')
                    if write:
                        item=plans['free'];row=db.execute('SELECT status,note FROM business_editorial_versions WHERE id=?',(item['version'],)).fetchone()
                        assert row[0]==write,(name,'owner action outcome',row)
                        if write=='changes_requested':assert row[1]==post['note']
                        if write=='published':assert db.execute('SELECT publication_status FROM businesses WHERE id=?',(item['business'],)).fetchone()[0]=='published'
                        for table,rows in protected.items():assert list(db.execute('SELECT * FROM '+table+' ORDER BY 1,2'))==rows,(name,'unrelated contract/payment changed',table)
                    else:assert list(db.iterdump())==initial,(name,'read or rejected action wrote database data')
                    outcome=stable_database(db)
                results.append((response,parsed.events,parsed.resources,outcome))
            assert results[0]==results[1],(name,next(((a,b) for a,b in zip(results[0][1],results[1][1]) if a!=b),'session/resources/data or markup length differs'))
            (stored_checks if stored else checks).append({'case':name,'http':status,'markup_resources_authorization_data_and_owner_outcomes_preserved':True})
        token='signed-workspace-token';free=plans['free'];base={'csrf':token,'business_id':str(free['business']),'version_id':str(free['version']),'payload_hash':None}
        with closing(sqlite3.connect(seed)) as db:base['payload_hash']=db.execute('SELECT payload_hash FROM business_editorial_versions WHERE id=?',(free['version'],)).fetchone()[0]
        for locale in ['es','en','pt-br']:
            q={'lang':locale}
            for state in ['draft','in_editorial','awaiting_owner','owner_approved','changes_requested','rejected','published']:compare(state+'/'+locale,q,state=state)
            for variant in ['empty','unpublished','expired','private-place','payment','clarification','validation','editorial']:compare(variant+'/'+locale,q,variant=variant)
            compare('administrator/'+locale,q,mode='admin')
            for flag in [{'resultado':'publicado'},{'resultado':'correcciones'},{'pago':'enviado'},{'solicitud':'guardada'},{'cupon':'aplicado'},{'resultado':'publicado','pago':'enviado','solicitud':'guardada','cupon':'aplicado'}]:compare('notice/'+json.dumps(flag)+'/'+locale,q|flag)
            compare('legacy-space/'+locale,q|{'espacio':'business'},omit_tab=True)
            compare('invalid-tab/'+locale,q|{'tab':'invalid'},status=400)
            compare('csrf-publish/'+locale,q,base|{'action':'owner_publish','csrf':'invalid'},status=303)
            compare('csrf-changes/'+locale,q,base|{'action':'owner_changes','csrf':'invalid'},status=303)
            compare('unknown-action/'+locale,q,base|{'action':'unknown'},status=400)
            compare('foreign-business/'+locale,q,base|{'action':'owner_publish'},mode='intruder',status=400)
            compare('stale-version/'+locale,q,base|{'action':'owner_publish','version_id':'99999999'},status=400)
            compare('stale-hash/'+locale,q,base|{'action':'owner_changes','payload_hash':'invalid','note':'Cambios de prueba'},state='awaiting_owner',status=400)
            compare('short-note/'+locale,q,base|{'action':'owner_changes','note':'short'},state='awaiting_owner',status=400)
            compare('long-note/'+locale,q,base|{'action':'owner_changes','note':'x'*201},state='awaiting_owner',status=400)
            compare('changes-requested/'+locale,q,base|{'action':'owner_changes','note':'Corrige este texto de prueba.'},state='awaiting_owner',status=303,write='changes_requested')
            compare('idempotent-publication/'+locale,q,base|{'action':'owner_publish'},state='published',status=303)
            compare('publication/'+locale,q,base|{'action':'owner_publish'},state='awaiting_owner',status=303,write='published')
            compare('continue-payment/'+locale,q|{'solicitud':'guardada','business':str(plans['page']['business'])},variant='payment',status=303)
            for mode in ['guest','expired','invalid_proof','disabled']:compare(mode+'/'+locale,q,mode=mode,status=303)
        for locale in ['en','pt-br']:compare('stored-data/'+locale,{'lang':locale},stored=True)
        icon_checks=[]
        for name in ['edit','payment','card','page','publish','comment','external','info']:
            old_helper=before/'public/business/_workspace-icon.php'
            old=subprocess.check_output(['php','-r','require $argv[1];echo '+('workspace_icon' if old_helper.is_file() else 'BusinessWorkspacePresentation::icon')+'($argv[2]);',str(old_helper if old_helper.is_file() else before/'app/BusinessWorkspacePresentation.php'),name])
            new=subprocess.check_output(['php','-r','require $argv[1];echo BusinessWorkspacePresentation::icon($argv[2]);',str(after/'app/BusinessWorkspacePresentation.php'),name])
            assert old==new,name;icon_checks.append(name)
        output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'isolated_cli_sqlite_fixtures':True,'no_external_mail_or_publication':True,'controller_processing_identical_except_removed_presentation_imports':True,'checks':checks,'stored_data_checks':stored_checks,'icons_identical':icon_checks},indent=2)+'\n')
    print(f'OK: {len(checks)} workspace comparisons, {len(stored_checks)} stored-data regressions and {len(icon_checks)} identical icons; owner decisions, payments, drafts, QR and access preserved.')
if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    for name in ['before','after','database','output']:p.add_argument('--'+name,type=Path,required=True)
    run(**vars(p.parse_args()))
