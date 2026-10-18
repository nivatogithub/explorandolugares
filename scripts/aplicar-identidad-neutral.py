"""Apply approved neutral identity to a prepared local site, without deployment."""
from pathlib import Path
import argparse,json,re,shutil
ROOT=Path(__file__).resolve().parents[1]
def apply(public):
 public=Path(public);assets=public/'assets';assets.mkdir(exist_ok=True,parents=True)
 tokens=json.loads((ROOT/'design/portal/tokens.json').read_text())['colors']
 variables=';'.join('--el-'+k+':'+v for k,v in tokens.items())
 css='''/* Explorando Lugares · identidad neutral v2 */
:root{VARIABLES}
body{background:var(--el-page)!important;color:var(--el-text)}
header,footer{background:var(--el-page)!important;border-color:var(--el-border)!important}
.pilot-banner{display:none!important}
h1,h2,h3,h4{color:var(--el-text)}
.card,.directory-card,.destination-experience-card,.highlight-grid article,.metric-card{background:var(--el-card)!important;border-color:var(--el-border)!important}
.destination-invitation,.destination-reasons,.destination-nav,.destination-preview-banner{background:var(--el-panel)!important;border-color:var(--el-border)!important}
input,select,textarea{background:var(--el-field);border-color:var(--el-control-border)}
.destination-direct-reading .destination-overview{display:block!important;grid-template-columns:1fr!important;padding:28px 0!important}
.destination-direct-reading .destination-overview>div,.destination-direct-reading .destination-overview p{width:100%;max-width:none!important}
.destination-direct-reading .destination-overview h2{font-size:clamp(25px,3vw,34px);margin:0 0 12px}
.destination-direct-reading .compact-section-heading{display:block;margin:0 0 18px;padding:0}
.destination-direct-reading .compact-section-heading h2{font-size:clamp(25px,3vw,34px);line-height:1.15;margin:0}
.destination-direct-reading #que-conocer,.destination-direct-reading .destination-experiences,.destination-direct-reading #lugares{padding-top:28px;padding-bottom:28px}
.destination-direct-reading .highlight-grid article>p,.destination-direct-reading .destination-experience-copy>p{font-size:14px;line-height:1.45;margin:8px 0 12px}
.destination-direct-reading .highlight-grid article h3,.destination-direct-reading .destination-experience-copy h4{font-size:18px;line-height:1.2;margin:8px 0}
.destination-direct-reading .highlight-grid article>h3,.destination-direct-reading .highlight-grid article>p{padding-left:12px;padding-right:12px}
.destination-direct-reading .directory-card-body{padding:12px 14px}
.destination-direct-reading .destination-invitation{padding:22px;display:flex;align-items:center;justify-content:space-between;gap:20px}
.destination-direct-reading .destination-invitation h2{font-size:24px;margin:0}
.destination-direct-reading .destination-invitation p{margin:6px 0 0}
@media(max-width:650px){.destination-direct-reading .destination-invitation{display:block}.destination-direct-reading .destination-invitation>.pill{margin-top:14px}}

'''.replace('VARIABLES',variables)
 (assets/'identidad-neutral.css').write_text(css)
 shutil.copy2(ROOT/'backend/public/assets/identidad-neutral.js',assets/'identidad-neutral.js') if assets.resolve()!=(ROOT/'backend/public/assets').resolve() else None
 # Replace only known former surface/border colours. Preserve map/status colours,
 # illustration SVGs, logos and photographic pixels.
 palette={'#fffaf2':'#fafafa','#fff0dc':'#f1f2f4','#fff8ed':'#ffffff','#fffdf8':'#ffffff','#ffe4ba':'#e9ebee','#f2c77e':'#dadde2','#e9b75f':'#bdc2cb','#fff3dc':'#f1f2f4','#fff4df':'#f1f2f4','#fff7e8':'#ffffff','#fff5df':'#f1f2f4','#fff9ec':'#ffffff','#f0d39a':'#dadde2','#e8efeb':'#f1f2f4','#dfe9e3':'#e9ebee','#d7e0da':'#dadde2','#bacac1':'#bdc2cb','#faf9f5':'#fafafa','#f5b126':'#bdc2cb'}
 css_count=0
 for p in assets.rglob('*.css'):
  if p.name in {'leaflet.css','identidad-neutral.css'}:continue
  s=p.read_text();new=re.sub('|'.join(palette),lambda m:palette[m[0].lower()],s,flags=re.I)
  if new!=s:p.write_text(new);css_count+=1
 hook='<link rel="stylesheet" href="/assets/identidad-neutral.css?v=3"><script defer src="/assets/identidad-neutral.js?v=3"></script>'
 count=0
 for p in public.rglob('*'):
  if p.suffix not in {'.html','.php'}:continue
  s=p.read_text()
  if '</head>' not in s:continue
  s=re.sub(r'<!-- etapas-piloto -->.*?<!-- /etapas-piloto -->','',s,flags=re.S)
  s=re.sub(r'<aside\b[^>]*class="pilot-banner"[^>]*>.*?</aside>','',s,flags=re.S)
  for old,new in {'Comenzamos por Jericó.':'Destinos para descubrir.','La primera guía ya está disponible. Abriremos otros destinos cuando revisemos su información y sus imágenes.':'Explora las guías disponibles.','Começamos por Jericó.':'Destinos para descobrir.','We start with Jericó.':'Destinations to discover.','Starting with Jericó.':'Destinations to discover.'}.items():s=s.replace(old,new)
  s=s.replace('identidad-neutral.js?v=2','identidad-neutral.js?v=3').replace('identidad-neutral.css?v=2','identidad-neutral.css?v=3')
  if 'identidad-neutral.css' not in s:s=s.replace('</head>',hook+'</head>',1)
  if s!=p.read_text():p.write_text(s);count+=1
 print(json.dumps({'public':str(public),'updated_pages':count,'updated_stylesheets':css_count,'published':False}))
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);a=p.parse_args();apply(a.public)
