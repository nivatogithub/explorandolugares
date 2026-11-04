#!/usr/bin/env python3
"""Compare demo cards with isolated data, including their inert contact links."""
from pathlib import Path
from importlib.util import spec_from_file_location, module_from_spec
import json, os, shutil, subprocess, tempfile

ROOT=Path(__file__).resolve().parents[1]
BASE=ROOT/'.local/architecture-demo-cards-final'
spec=spec_from_file_location('auth',ROOT/'scripts/check-account-auth-views.py')
auth=module_from_spec(spec);spec.loader.exec_module(auth)
PHP=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
ini_set('session.save_path',$i['sessions']);session_id(bin2hex(random_bytes(16)));session_start();
$_GET=$i['get'];$_POST=[];
$_SERVER=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/business/demo.php','HTTP_HOST'=>'127.0.0.1','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['package'].'/public'];
ob_start();register_shutdown_function(static function(){while(ob_get_level()>1)ob_end_flush();echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});
require $i['package'].'/public/business/demo.php';
'''
with tempfile.TemporaryDirectory(prefix='demo-cards-',dir=ROOT/'.local') as tmp:
    packages=[]
    for label,source in [('before',BASE),('after',ROOT/'.local/architecture-demo-pages-final')]:
        package=Path(tmp)/label
        shutil.copytree(source/'app',package/'app')
        shutil.copytree(source/'public',package/'public',ignore=shutil.ignore_patterns('assets'))
        (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
        (package/'private/sessions').mkdir(parents=True)
        shutil.copy2(BASE/'private/presentation.sqlite',package/'private/fixture.sqlite')
        config={'dsn':'sqlite:'+str(package/'private/fixture.sqlite'),'app_key':'isolated-demo-test','local_test':True}
        (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
        packages.append(package)
    cases=[]
    for view in ['cards','free','featured','page','page_premium']:
        for embedded in [False,True]:
            for presentation in (['card','page'] if view in ['page','page_premium'] else ['card']):
                query={'view':view,'presentation':presentation}
                if embedded:query['embedded']='1'
                responses=[]
                for package in packages:
                    payload={'package':str(package),'sessions':str(package/'private/sessions'),'get':query}
                    result=subprocess.run(['php','-d','display_errors=stderr','-r',PHP],input=json.dumps(payload),text=True,capture_output=True,env=dict(os.environ,EL_PREPROD_LOCAL='1'),check=True)
                    assert not result.stderr.strip(),result.stderr
                    response=json.loads(result.stdout);assert response['http']==200,(query,response['http'])
                    normalized=subprocess.check_output(['php','-r',"libxml_use_internal_errors(true);$d=new DOMDocument();$d->loadHTML('<?xml encoding=\"UTF-8\">'.stream_get_contents(STDIN),LIBXML_NONET);echo $d->saveHTML();"],input=response['html'],text=True)
                    parsed=auth.AuthMarkup(package/'public','/business/demo.php');parsed.feed(normalized.replace('?v=business-pages-v2',''))
                    for event in parsed.events:
                        if event[0]=='start' and event[1]=='a':
                            attrs=dict(event[2]);classes=attrs.get('class','').split()
                            if any(c in classes for c in ['directory-contact-link','business-channel','footer-contact-link']):
                                assert 'href' not in attrs and attrs.get('aria-disabled')=='true',(query,event)
                    responses.append(parsed.events)
                if responses[0]!=responses[1]:
                    for index,(left,right) in enumerate(zip(*responses)):
                        if left!=right:raise AssertionError((query,index,left,right))
                    raise AssertionError((query,len(responses[0]),len(responses[1])))
                cases.append(query)
    print('OK:',len(cases),'demo comparisons; contact links remain inert.')
