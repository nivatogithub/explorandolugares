"""Check rendered public anchors and their actual destination languages on preprod."""
import json
import re
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from urllib.parse import urlsplit, urljoin
from urllib.request import build_opener, HTTPRedirectHandler
from xml.etree import ElementTree

BASE = 'http://127.0.0.1:8088'
HOSTS = {'127.0.0.1', 'explorandolugares.com', 'www.explorandolugares.com'}
class LocalRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        target = urlsplit(newurl)
        if target.hostname not in HOSTS:
            raise ValueError('external redirect')
        return super().redirect_request(req, fp, code, msg, headers, BASE+target.path+('?' + target.query if target.query else ''))

class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.lang = ''
        self.links = set()
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='html': self.lang=(a.get('lang') or '').lower()
        if tag=='iframe' and a.get('src'):
            self.links.add(a['src'])
        if tag=='a' and 'data-language-target' not in a and 'hreflang' not in a:
            href=a.get('href') or ''
            if href and not href.startswith('#'): self.links.add(href)

def fetch(path):
    return build_opener(LocalRedirect()).open(BASE+path,timeout=20)
def read(path):
    parsed=Page()
    try:
        with fetch(path) as response:
            if response.headers.get_content_type()=='text/html':
                parsed.feed(response.read().decode('utf-8'))
        return parsed.lang,parsed.links
    except Exception as e: return str(e),set()

def internal(path,href):
    u=urlsplit(urljoin(BASE+path,href))
    if u.hostname not in HOSTS or u.scheme not in ('http','https'):return None
    if re.search(r'/(?:admin|api|assets|logout|salir|cerrar-sesion)(?:/|\.|$)',u.path):return None
    return u.path+('?' +u.query if u.query else '')

def audit():
    xml=ElementTree.fromstring(fetch('/sitemap.xml').read())
    roots={urlsplit(x.text).path for x in xml.iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')}
    pages={};pending=set(roots)
    with ThreadPoolExecutor(max_workers=5) as pool:
        while pending:
            keys=sorted(pending);pages.update(zip(keys,pool.map(read,keys)));pending=set()
            for path in keys:
                for href in pages[path][1]:
                    target=internal(path,href)
                    if target and target not in pages:pending.add(target)
            if len(pages)+len(pending)>5000:raise RuntimeError('Unexpected number of distinct routes')
    errors=[];edges=0
    for path,(lang,links) in pages.items():
        if lang not in ('es','en','pt-br'):errors.append([path,'',lang]);continue
        for target in {internal(path,h) for h in links}-{None}:
            edges+=1
            if pages[target][0]!=lang:errors.append([path,target,pages[target][0]])
    for path in roots:
        expected=next((l for l in ('en','pt-br') if path.startswith('/'+l+'/')),'es')
        if pages[path][0]!=expected: errors.append([path,'sitemap language',expected])
    return dict(places=len([p for p in roots if re.fullmatch(r'/lugares/[^/]+/',p)]),pages=len(pages),edges=edges,errors=errors)

if __name__=='__main__':
    report=audit()
    print(json.dumps(report,ensure_ascii=False))
    raise SystemExit(bool(report['errors']))
