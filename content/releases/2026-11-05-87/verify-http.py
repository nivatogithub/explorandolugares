"""Verify the 87 public guides and their source media against local preproduction."""
import hashlib
import json
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import urlopen
from xml.etree import ElementTree

folder = Path(__file__).resolve().parent
base = 'http://127.0.0.1:8088'
content = json.loads((folder/'public-content.json').read_text())
manifest = json.loads((folder/'manifest.json').read_text())
media = {m['id']: '/' + m['asset_path'] for m in content['tables']['media_assets']}

def fetch(path):
    with urlopen(base+path, timeout=30) as response:
        if response.geturl() != base+path:
            raise ValueError('Unexpected redirect: '+path)
        return response.read()

class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.images, self.cards, self.canonical = set(), [], None
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag=='img': self.images.add(a.get('src',''))
        if tag=='article' and 'data-place-slug' in a: self.cards.append(a['data-place-slug'])
        if tag=='link' and a.get('rel')=='canonical': self.canonical=a['href']

def page(path):
    result=Page()
    result.feed(fetch(path).decode())
    return result

def check_guide(job):
    prefix,slug,guide=job
    path=f'/{prefix}lugares/{slug}/'
    rendered=page(path)
    assert rendered.canonical=='https://explorandolugares.com'+path,path
    expected={media[m['media_asset_id']] for m in guide['destination_media'].values() if m['role']=='hero'}
    expected.update(media[x['media_asset_id']] for x in guide['places'] if x.get('media_asset_id'))
    assert expected <= rendered.images,(path,expected-rendered.images)
    return path

def check_media(item):
    assert hashlib.sha256(fetch('/'+item['path'])).hexdigest()==item['sha256'],item['path']

jobs=[(prefix,slug,guide) for slug,guide in content['editorial'].items() for prefix in ('','en/','pt-br/')]
with ThreadPoolExecutor(max_workers=4) as workers:
    urls=list(workers.map(check_guide,jobs))
    list(workers.map(check_media,manifest['media']))
sitemap=ElementTree.fromstring(fetch('/sitemap.xml'))
indexed={e.text for e in sitemap.iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')}
assert all('https://explorandolugares.com'+u in indexed for u in urls)
def filter_page(**params): return page('/lugares/?'+urlencode(params)).cards
assert 'el-retiro' in filter_page(q='la fe')
assert 'el-retiro' not in filter_page(q='la fe',heritage=1)
assert filter_page(q='cascadas')==filter_page(q='cascada') and filter_page(q='cascada')
assert filter_page(q='charcos')==filter_page(q='charco') and filter_page(q='charco')
assert set(filter_page(heritage=1,department='Santander'))=={'giron','socorro','barichara'}
assert len(set(filter_page(heritage=1)+filter_page(heritage=1,pagina=2)))==18
print(json.dumps(dict(guides=87,language_routes=len(urls),media_verified=len(manifest['media']),sitemap_routes=len(urls),heritage_towns=18,filters='passed')))
