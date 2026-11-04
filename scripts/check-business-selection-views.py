#!/usr/bin/env python3
"""Compare subscription business selection using isolated real controllers and file-backed sessions."""
from contextlib import closing
from importlib.util import spec_from_file_location,module_from_spec
from pathlib import Path
import argparse,json,os,shutil,sqlite3,subprocess,tempfile
from html import unescape
ROOT=Path(__file__).resolve().parents[1]
def module(name,file):
    spec=spec_from_file_location(name,ROOT/'scripts'/file);value=module_from_spec(spec);spec.loader.exec_module(value);return value
profile=module('selection_profile','check-account-profile-views.py');editor=module('selection_editor','check-editorial-layout.py')
PHP=profile.PHP.replace("require $i['package'].'/public/business/dashboard.php';","require $i['package'].'/public/business/checkout.php';")
PHP=PHP.replace("$_GET=$i['get'];",r"""
if($i['intent']!==null){$_SESSION['subscription_purchase_intent']=$i['intent'];$_SESSION['subscription_purchase_intent']['created_at']=time()-($i['intent_age']??0);}
$_SESSION['subscription_purchase_selected']=987654321;
$_GET=$i['get'];
""")
PHP=PHP.replace("'space'=>$_SESSION['account_space']??null,", "'space'=>$_SESSION['account_space']??null,'selection'=>$_SESSION['subscription_purchase_selected']??null,'intent'=>isset($_SESSION['subscription_purchase_intent'])?array_diff_key($_SESSION['subscription_purchase_intent'],['created_at'=>1]):null,")
def processing(package):
    source=(package/'public/business/checkout.php').read_text()
    markers=["require_once __DIR__.'/../_account-page.php';","require_once dirname(__DIR__).'/_portal-shell.php';"]
    return source[:min(source.index(m) for m in markers if m in source)]
def run(before,after,database,output):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(path.is_relative_to(ROOT/'.local') for path in [before,after,database])
    assert processing(before)==processing(after),'Selection processing changed'
    checks=[]
    with tempfile.TemporaryDirectory(prefix='business-selection-',dir=ROOT/'.local') as temporary:
        root=Path(temporary);seed=root/'seed.sqlite';shutil.copy2(database,seed)
        prepared=editor.php(editor.SEED,{'package':str(before),'database':str(seed)});owner,admin=prepared['owner'],prepared['admin'];business=prepared['plans']['free']['business']
        with closing(sqlite3.connect(seed)) as db:
            place=db.execute('SELECT destination_id FROM businesses WHERE id=?',(business,)).fetchone()[0]
            experience=db.execute('INSERT INTO points_of_interest(destination_id,slug,poi_type,publication_status) VALUES(?,"selection-fixture","experience","published")',(place,)).lastrowid
            db.execute('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description) VALUES(?,"es","Experiencia de prueba","")',(experience,))
            other_place=db.execute('SELECT id FROM destinations WHERE id<>? AND publication_status="published" LIMIT 1',(place,)).fetchone()[0]
            other_experience=db.execute('INSERT INTO points_of_interest(destination_id,slug,poi_type,publication_status) VALUES(?,"selection-fixture","experience","published")',(other_place,)).lastrowid
            db.execute('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description) VALUES(?,"es","Experiencia de otro lugar","")',(other_experience,))
            db.commit()
        packages=[]
        for label,source in [('before',before),('after',after)]:
            package=root/label;shutil.copytree(source/'app',package/'app');shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
            (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True);(package/'private/sessions').mkdir(parents=True)
            config={'dsn':'sqlite:'+str(package/'private/fixture.sqlite'),'app_key':'selection-regression-key','local_test':True,'business_accounts_enabled':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
            (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);');packages.append(package)
        intent={'plan':'page','period':'annual','operator':False,'experience_id':0,'destination_id':None}
        def compare(name,query=None,mode='owner',saved=intent,age=0,variant='',status=200,selected='unchecked',remembered=None):
            outcomes=[]
            for package in packages:
                fixture=package/'private/fixture.sqlite';shutil.copy2(seed,fixture)
                with closing(sqlite3.connect(fixture)) as db:
                    if variant=='empty':db.execute('DELETE FROM business_account_memberships WHERE account_id=?',(owner,))
                    if variant=='inactive-membership':db.execute('UPDATE business_account_memberships SET status="revoked" WHERE account_id=?',(owner,))
                    if variant=='eligible':db.execute('INSERT INTO business_categories(business_id,category_code) VALUES(?,"experiencias")',(business,))
                    if variant=='disabled':db.execute('UPDATE accounts SET status="disabled" WHERE id=?',(owner,))
                    if variant=='no-price':db.execute('DELETE FROM subscription_plan_prices WHERE plan_code="page"')
                    if variant=='stored':
                        db.execute('UPDATE business_translations SET display_name="Nombre" WHERE business_id=? AND locale="es"',(business,))
                        db.execute('UPDATE destination_translations SET display_name="Tu cuenta" WHERE destination_id=? AND locale="es"',(place,))
                    db.commit();initial=list(db.iterdump())
                query=query or {};route='/business/checkout.php'
                request={'package':str(package),'fixture':str(fixture),'sessions':str(package/'private/sessions'),'owner':owner,'admin':admin,'mode':mode,'remembered':remembered,'get':query,'post':None,'route':route,'intent':saved,'intent_age':age}
                result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(request),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
                assert not result.stderr.strip(),(name,result.stderr[:800]);response=json.loads(result.stdout);html=response.pop('html');assert response['http']==status,(name,response['http'],html[-500:])
                if selected!='unchecked':assert response['selection']==selected,(name,'selection not preserved',response['selection'])
                if status==200:
                    assert '¿Para cuál negocio?' in unescape(html) and 'href="/negocios-locales/suscripciones/"' in html,(name,'missing selector or canonical simulator')
                    if variant=='stored':assert 'Nombre · Tu cuenta' in unescape(html),(name,'stored business/place names translated')
                    if variant in ['empty','inactive-membership']:assert '?business=' not in html,(name,'unowned options exposed')
                    elif mode=='owner':assert '?business='+str(business) in html,(name,'owned business missing')
                parser=profile.ProfileMarkup(package/'public');parser.route=route;parser.feed(html)
                with closing(sqlite3.connect(fixture)) as db:assert list(db.iterdump())==initial,(name,'selection wrote business/payment data')
                outcomes.append((response,parser.events,parser.resources))
            assert outcomes[0]==outcomes[1],(name,next(((a,b) for a,b in zip(outcomes[0][1],outcomes[1][1]) if a!=b),'response/resources/length differ'))
            checks.append({'case':name,'http':status,'presentation_resources_session_and_unchanged_database_preserved':True})
        for locale in ['es','en','pt-br']:
            q={'lang':locale}
            compare('remembered/'+locale,remembered=locale)
            compare('empty/'+locale,q,variant='empty')
            compare('inactive-membership/'+locale,q,variant='inactive-membership')
            compare('administrator/'+locale,q,mode='admin')
            compare('stored-labels/'+locale,q,variant='stored')
            compare('missing-intent/'+locale,q,saved=None,status=400)
            compare('expired-intent/'+locale,q,age=7201,status=400)
            compare('missing-timestamp/'+locale,q,age=2000000000,status=400)
            compare('guest-missing-intent/'+locale,q,mode='guest',saved=None,status=400)
            for mode in ['guest','expired','invalid_proof']:compare(mode+'/'+locale,q,mode=mode,status=303)
            compare('disabled-account/'+locale,q,variant='disabled',status=303)
            compare('foreign-business/'+locale,q|{'business':'999999999'},status=400)
            compare('owned-selection/'+locale,q|{'business':str(business)},status=303,selected=business)
            compare('operator-ineligible/'+locale,q|{'business':str(business)},saved=intent|{'operator':True},status=400)
            compare('operator-eligible/'+locale,q|{'business':str(business)},saved=intent|{'operator':True},variant='eligible',status=303,selected=business)
            compare('operator-experience/'+locale,q|{'business':str(business)},saved=intent|{'operator':True,'experience_id':experience,'destination_id':place},variant='eligible',status=303,selected=business)
            compare('operator-other-place/'+locale,q|{'business':str(business)},saved=intent|{'operator':True,'experience_id':other_experience,'destination_id':other_place},variant='eligible',status=400)
            compare('invalid-plan/'+locale,q|{'plan':'forged'},status=400)
            compare('invalid-period/'+locale,q|{'plan':'page','period':'weekly'},status=400)
            compare('free-operator/'+locale,q|{'plan':'free','operator':'1'},status=400)
            compare('no-price/'+locale,q|{'plan':'page'},variant='no-price',status=400)
            compare('invalid-experience/'+locale,q|{'plan':'page','experience':'999999999'},status=400)
            compare('capture-experience/'+locale,q|{'plan':'page','experience':str(experience)})
            for plan in ['free','featured','page','page_premium']:
                for period in ['monthly','quarterly','semiannual','annual']:
                    compare('capture/'+plan+'/'+period+'/'+locale,q|{'plan':plan,'period':period},selected=None)
            compare('capture-clears-previous-selection/'+locale,q|{'plan':'free'},selected=None)
        output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'isolated_cli_sqlite_fixtures':True,'http_session_validator_invoked_explicitly':True,'controller_processing_identical':True,'database_writes':False,'real_mail_sent':False,'grouped_payment_implemented':False,'checks':checks},indent=2)+'\n')
    print(f'OK: {len(checks)} business selection comparisons; plans, periods, operator eligibility, account ownership, sessions, resources and data preserved.')
if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    for key in ['before','after','database','output']:parser.add_argument('--'+key,type=Path,required=True)
    run(**vars(parser.parse_args()))
