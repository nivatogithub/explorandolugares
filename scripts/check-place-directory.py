#!/usr/bin/env python3
"""Check actual directory responses in an isolated built package, without a server."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import parse_qs, urlsplit, urlencode
import argparse, json, os, re, subprocess

ROOT=Path(__file__).resolve().parents[1]

class DirectoryPage(HTMLParser):
    def __init__(self):
        super().__init__();self.cards=[];self.forms=[];self.links=[];self.canonical=[];self.meta={};self.languages={};self.resources=[];self.script=False;self.schemas=[]
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='article' and 'data-place-slug' in a:self.cards.append(a['data-place-slug'])
        if tag=='form':self.forms.append(a)
        if tag=='a':self.links.append(a)
        if tag=='meta':self.meta[a.get('name') or a.get('property')]=a.get('content')
        if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href'))
        if tag=='link' and a.get('rel')=='alternate':self.languages[a.get('hreflang')]=a.get('href')
        if tag=='link' and a.get('rel')=='stylesheet':self.resources.append(a.get('href'))
        if tag=='script' and a.get('type')=='application/ld+json':self.script=True;self.schemas.append('')
    def handle_endtag(self,tag):
        if tag=='script':self.script=False
    def handle_data(self,data):
        if self.script:self.schemas[-1]+=data

def run(package,output):
    package=package.resolve()
    if not package.is_relative_to(ROOT/'.local') or not (package/'private/presentation.sqlite').is_file():raise ValueError('Disposable package required.')
    checks=[];catalog=json.loads((ROOT/'content/release-places-businesses.json').read_text());slugs=set(catalog['public_places'])
    def render(locale,query):
        query={'locale':locale,**query};prefix={'es':'','en':'/en','pt-BR':'/pt-br'}[locale];route=prefix+'/lugares/'
        if len(query)>1:route+='?'+urlencode({k:v for k,v in query.items() if k!='locale'},doseq=True)
        code='register_shutdown_function(static function(){fwrite(STDERR,"HTTP_STATUS:".(http_response_code()?:200));});$_SESSION=[];$_SERVER["REQUEST_URI"]='+json.dumps(route)+';$_SERVER["REQUEST_METHOD"]="GET";$_GET=json_decode('+json.dumps(json.dumps(query))+',true);require '+json.dumps(str(package/'public/places.php'))+';'
        env={k:v for k,v in os.environ.items() if not k.startswith('EL_DESTINATIONS_') and k!='EL_PUBLIC_BASE_URL'}
        result=subprocess.run(['php','-d','display_errors=stderr','-r',code],text=True,capture_output=True,env=env)
        status=re.search(r'HTTP_STATUS:(\d+)$',result.stderr)
        assert result.returncode==0 and status and not result.stderr[:status.start()].strip(),result.stderr
        parsed=DirectoryPage();parsed.feed(result.stdout);return int(status[1]),parsed,result.stdout,route
    for locale in ['es','en','pt-BR']:
        seen=[]
        for page in range(1,4):
            status,parsed,html,route=render(locale,{} if page==1 else {'pagina':str(page)})
            assert status==200 and len(parsed.cards)==min(9,len(slugs)-9*(page-1))
            assert not set(seen)&set(parsed.cards);seen+=parsed.cards
            assert parsed.canonical==['https://explorandolugares.com'+route]
            assert parsed.languages[locale]==parsed.canonical[0] and len(parsed.languages)==4
            assert parsed.meta['robots']=='index,follow' and parsed.meta['og:url']==parsed.canonical[0]
            assert any(f.get('method')=='get' for f in parsed.forms)
            assert len([r for r in parsed.resources if '/place-directory.css?v=' in r])==1
            assert len([r for r in parsed.resources if '/places.css?v=' in r])==1
            assert '/assets/place-directory.js?v=' in html
            assert '<noscript><button type="submit"' in html
            assert 'places.js' not in html and 'name="heritage"' in html and 'name="region"' in html and 'name="department"' in html
            assert html.index('name="region"')<html.index('name="department"')
            assert html.count('class="place-heritage-star"')==len(set(parsed.cards)&{'jardin','jerico','santa-fe-de-antioquia'})
            items=json.loads(parsed.schemas[0])['@graph'][0]['mainEntity']['itemListElement'];assert len(items)==len(parsed.cards) and items[0]['position']==(page-1)*9+1
            checks.append({'locale':locale,'page':page,'http':status,'cards':len(parsed.cards),'crawlable_and_server_rendered':True})
        assert set(seen)==slugs
        cases=[({'heritage':'1'},{'jardin','jerico','santa-fe-de-antioquia'}),({'q':'Jerico'},{'jerico'}),({'q':'Jericó'},{'jerico'}),({'department':'Cesar'},{'valledupar'}),({'region':'Caribe'},{'cartagena','santa-marta','riohacha','valledupar','barranquilla','san-bernardo-del-viento'}),({'q':'j','department':'Antioquia','heritage':'1'},{'jardin','jerico'}),({'q':'no-such-place'},set()),({'q':'%'},set()),({'department':"' OR 1=1 --"},set()),({'q':'<script>alert(1)</script>'},set())]
        for query,expected in cases:
            status,parsed,html,route=render(locale,query);assert status==200 and set(parsed.cards)==expected,(locale,query,parsed.cards)
            assert parsed.meta['robots']=='noindex,follow' and '<script>alert(1)</script>' not in html
            assert 'data-remove-filter' not in html and 'data-clear-filter=' not in html
            assert 'data-filter-disclosure' not in html and '<details' not in html
            assert 'data-clear-filters' in html
            for a in parsed.links:
                if 'data-clear-filters' in a:assert a['href']=={'es':'','en':'/en','pt-BR':'/pt-br'}[locale]+'/lugares/'
            checks.append({'locale':locale,'query':query,'cards':len(expected),'http':200})
        status,parsed,html,route=render(locale,{'q':'a'});next_link=next(a['href'] for a in parsed.links if a.get('rel')=='next')
        query=parse_qs(urlsplit(next_link).query);assert query['q']==['a'] and query['pagina']==['2']
        for language,url in parsed.languages.items():assert parse_qs(urlsplit(url).query)['q']==['a']
        checks.append({'locale':locale,'filter_links_preserved':True})
        assert render(locale,{'pagina':'999999'})[0]==302
        assert render(locale,{'q':[],'pagina':[]})[0]==200
    output.write_text(json.dumps({'checks':checks,'production_accessed':False,'database':'disposable SQLite'},ensure_ascii=False,indent=2)+'\n')
    print(f'OK: {len(checks)} directory response cases, 9 places per page, filters, languages, links and metadata.')

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--package',type=Path,required=True);parser.add_argument('--output',type=Path,required=True);args=parser.parse_args();run(args.package,args.output)
