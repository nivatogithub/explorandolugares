#!/usr/bin/env python3
"""Compare home content and the reusable dialog background without starting a server."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin,urlsplit,parse_qs
import argparse,hashlib,json,subprocess

ROOT=Path(__file__).resolve().parents[1]

class MainMarkup(HTMLParser):
    def __init__(self,public,base):
        super().__init__(convert_charrefs=True);self.public=public;self.base=base;self.events=[];self.active=False;self.mains=0
    def handle_starttag(self,tag,attrs):
        if tag=='main':self.active=True;self.mains+=1
        if not self.active:return
        values=[]
        for key,value in attrs:
            if key in ('href','src') and value:
                url=urlsplit(urljoin(self.base,value));value=url.geturl()
                if not url.netloc and url.path.startswith('/assets/'):
                    asset=self.public/url.path.lstrip('/');assert asset.is_file(),value
                    digest=hashlib.sha256(asset.read_bytes()).hexdigest();version=parse_qs(url.query).get('v')
                    if version:assert digest.startswith(version[0]),value
                    value='resource:'+url.path+':'+digest
            values.append((key,value))
        self.events.append(('start',tag,values))
    def handle_endtag(self,tag):
        if self.active:self.events.append(('end',tag))
        if tag=='main':self.active=False
    def handle_data(self,data):
        if self.active and data.strip():self.events.append(('text',data.strip()))

def run(before,after,output):
    before,after=before.resolve(),after.resolve()
    assert all(p.is_relative_to(ROOT/'.local') for p in (before,after))
    checks=[]
    for locale,prefix in [('es',''),('en','en/'),('pt-br','pt-br/')]:
        baseline=MainMarkup(before/'public','/'+prefix);baseline.feed((before/'public'/prefix/'index.html').read_text());assert baseline.mains==1
        payload={'app':str(after/'app'),'public':str(after/'public'),'locale':locale}
        code="$i=json_decode(stream_get_contents(STDIN),true);require $i['app'].'/PortalPresentation.php';PortalPresentation::setPublicRoot($i['public']);echo json_encode(PortalPresentation::homeMain($i['locale']),JSON_THROW_ON_ERROR);"
        rendered=subprocess.run(['php','-d','display_errors=stderr','-r',code],input=json.dumps(payload),text=True,capture_output=True,check=True);assert not rendered.stderr,rendered.stderr
        for mode,html in [('public',(after/'public'/prefix/'index.html').read_text()),('component',json.loads(rendered.stdout))]:
            if mode=='public':assert (before/'public'/prefix/'index.html').read_text().split('<head>',1)[1].split('</head>',1)[0]==html.split('<head>',1)[1].split('</head>',1)[0],(locale,'home head metadata/resources changed')
            candidate=MainMarkup(after/'public','/'+prefix);candidate.feed(html);assert candidate.mains==1
            assert baseline.events==candidate.events,(locale,mode,next(((a,b) for a,b in zip(baseline.events,candidate.events) if a!=b),'event length differs'))
            checks.append({'locale':locale,'mode':mode,'content_structure_resolved_links_and_media_bytes_identical':True})
    output.parent.mkdir(parents=True,exist_ok=True);output.write_text(json.dumps({'production_accessed':False,'checks':checks},indent=2)+'\n')
    print('OK: six public-home/shared-background comparisons preserve structure, copy, links and approved media in three languages.')

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    for name in ['before','after','output']:p.add_argument('--'+name,type=Path,required=True)
    run(**vars(p.parse_args()))
