#!/usr/bin/env python3
"""Compare the real private place controller before/after extracting its view."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit, urljoin, parse_qs
import argparse, hashlib, json, sqlite3, subprocess, shutil, tempfile

ROOT=Path(__file__).resolve().parents[1]

class Markup(HTMLParser):
    def __init__(self,public):
        super().__init__(convert_charrefs=True);self.public=public;self.events=[]
    def attributes(self,attrs):
        result=[]
        for name,value in attrs:
            if name in ('src','href') and value:
                url=urlsplit(urljoin('/admin/places.php',value))
                if url.path.startswith('/assets/') and not url.netloc:
                    path=self.public/url.path.lstrip('/');assert path.is_file(),url.path
                    digest=hashlib.sha256(path.read_bytes()).hexdigest()
                    version=parse_qs(url.query).get('v')
                    if version:assert digest.startswith(version[0]),(url.path,'resource version mismatch')
                    value='resource:'+url.path+':'+digest
                elif name=='href' and url.path.startswith('/admin/') and not url.netloc:
                    # Relative and root-based private links must reach the same route.
                    value=url.path+('?' + url.query if url.query else '')+('#'+url.fragment if url.fragment else '')
            result.append((name,value))
        return result
    def handle_starttag(self,tag,attrs):self.events.append(('start',tag,self.attributes(attrs)))
    def handle_startendtag(self,tag,attrs):self.events.append(('empty',tag,self.attributes(attrs)))
    def handle_endtag(self,tag):self.events.append(('end',tag))
    def handle_data(self,data):self.events.append(('data',data))
    def handle_decl(self,decl):self.events.append(('decl',decl))
    def handle_comment(self,data):self.events.append(('comment',data))

def compare(before,after,database,output):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(p.is_relative_to(ROOT/'.local') for p in (before,after,database))
    with sqlite3.connect(database) as db:
        place_id=db.execute("SELECT id FROM destinations WHERE slug='jerico'").fetchone()[0]
    cases=[('list',{}),('search',{'q':'jer'}),('empty_search',{'q':'zzzz-no-place'}),
           ('department',{'departamento':'Antioquia'}),('published',{'estado':'published'}),
           ('draft',{'estado':'draft'}),('unknown_status',{'estado':'unknown'}),
           ('combined',{'q':'jer','departamento':'Antioquia','estado':'published'}),
           ('create',{'tab':'crear'}),('edit',{'id':place_id}),('missing',{'id':999999}),
           ('invalid_tab',{'tab':'unknown'}),('negative_id',{'id':-1}),
           ('saved',{'id':place_id,'saved':'1'}),('starter',{'id':place_id,'saved':'starter'}),
           ('archived',{'saved':'archived'}),('deleted',{'saved':'deleted'}),
           ('escaped_filter',{'q':'<script>alert(1)</script>'}),('array_notice',{'saved':['1']}),
           ('invalid_action',{}, {'csrf':'isolated-test','action':'invalid'}),
           ('invalid_save',{'id':place_id},{'csrf':'isolated-test','action':'save','id':place_id,'slug':'!'}),
           ('invalid_csrf',{}, {'csrf':'wrong','action':'archive','id':place_id}),
           ('guest',{},None,False),('expired',{},None,'expired')]
    checks=[]
    for case in cases:
        name,get,*rest=case;post=rest[0] if rest else None;authorized=rest[1] if len(rest)>1 else True
        results=[]
        for package in (before,after):
            payload={'get':get,'post':post,'authorized':authorized,'controller':str(package/'public/admin/places.php'),'public':str(package/'public')}
            code=r'''
$i=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$_GET=$i['get'];$_POST=$i['post']??[];$_FILES=[];
$_SESSION=['csrf'=>'isolated-test'];
if($i['authorized'])$_SESSION+=['admin'=>true,'last_seen'=>time()-($i['authorized']==='expired'?3600:0)];
$_SERVER=['REQUEST_METHOD'=>$i['post']===null?'GET':'POST','REQUEST_URI'=>'/admin/places.php','HTTP_HOST'=>'candidate.localhost','REMOTE_ADDR'=>'127.0.0.1','DOCUMENT_ROOT'=>$i['public']];
ob_start();register_shutdown_function(static function(){echo json_encode(['http'=>http_response_code()?:200,'html'=>ob_get_clean()],JSON_THROW_ON_ERROR);});
require $i['controller'];
'''
            process=subprocess.run(['php','-d','display_errors=stderr','-r',code],input=json.dumps(payload),capture_output=True,text=True,check=True)
            assert not process.stderr.strip(),(name,process.stderr[:400])
            result=json.loads(process.stdout);markup=Markup(package/'public');markup.feed(result['html'])
            results.append((result['http'],markup.events))
        assert results[0]==results[1],(name,'controller response changed')
        checks.append({'case':name,'http':results[0][0],'markup_fields_resources_identical':True})
    output.parent.mkdir(parents=True,exist_ok=True)
    output.write_text(json.dumps({'production_accessed':False,'comparison':'HTML structure/text/attributes and resource bytes; asset origin/version length may differ','checks':checks},indent=2)+'\n')
    print(f'OK: {len(checks)} real-controller cases preserve fields, filters, notices, errors, CSRF responses, private access and resource bytes.')

def run(before,after,database,output):
    before,after,database=before.resolve(),after.resolve(),database.resolve()
    assert all(p.is_relative_to(ROOT/'.local') for p in (before,after,database))
    with tempfile.TemporaryDirectory(prefix='place-controller-',dir=ROOT/'.local') as temporary:
        packages=[]
        for label,source in [('before',before),('after',after)]:
            package=Path(temporary)/label;package.mkdir()
            shutil.copytree(source/'app',package/'app')
            (package/'public').mkdir();shutil.copytree(source/'public/admin',package/'public/admin')
            for name in ['_portal-shell.php','asset-manifest.json']:shutil.copy2(source/'public'/name,package/'public'/name)
            (package/'public/assets').symlink_to(source/'public/assets',target_is_directory=True)
            (package/'private').mkdir();fixture=package/'private/fixture.sqlite';shutil.copy2(database,fixture)
            config={'dsn':'sqlite:'+str(fixture),'app_key':'isolated-controller-comparison','local_test':True,'unified_admin_only':True,'account_mail':{'enabled':False}}
            (package/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
            packages.append(package)
        compare(*packages,database,output)

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--before',type=Path,required=True);p.add_argument('--after',type=Path,required=True);p.add_argument('--database',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();run(a.before,a.after,a.database,a.output)
