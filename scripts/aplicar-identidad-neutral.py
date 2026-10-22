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
.directory-contact-links{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;font-size:13px;align-items:center}
.directory-contact-links a{white-space:nowrap}
.directory-card .directory-card-body{display:flex;flex-direction:column;min-width:0;gap:0}
[data-directory-tier="featured"] .directory-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}
.directory-card-tier-featured{background:var(--el-card)!important;border:1px solid var(--el-control-border)!important;box-shadow:none!important}
.directory-card-tier-featured .directory-business-mark{display:none!important}
.directory-card-tier-featured .directory-card-body{display:grid!important;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:6px!important;padding:12px 14px!important}
.directory-card.directory-card-tier-featured .directory-business-title{font-size:14px!important;line-height:1.25!important}
.directory-card.directory-card-tier-featured .directory-icon-actions{margin:0!important;padding:0!important;align-self:center}
.directory-card-tier-featured .directory-compact-heading{grid-column:1;grid-row:1;min-width:0}
.directory-card-tier-featured .directory-icon-actions{grid-column:2;grid-row:1;width:auto!important}
.directory-card-tier-featured .directory-description{grid-column:1 / -1;grid-row:2}
.directory-card-tier-featured .directory-card-details{grid-column:1 / -1;grid-row:3;min-width:0}
.directory-card-tier-featured .directory-category-chip{padding:2px 5px!important;min-height:0!important;margin:0!important}
@media(max-width:900px){[data-directory-tier="featured"] .directory-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media(max-width:600px){[data-directory-tier="featured"] .directory-grid{grid-template-columns:1fr!important}}

.directory-card{min-height:0!important;height:auto!important}
.directory-card .directory-card-body{height:auto!important;min-height:0!important;grid-template-rows:none!important;padding:14px!important}
.directory-card .directory-icon-actions{width:100%;justify-content:flex-end!important;margin-top:10px!important;padding-top:0!important}
.directory-card .directory-status-note{font-size:12px;margin:8px 0}
.directory-card button.directory-icon-action{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:0;border:0;border-radius:9px;background:transparent;color:var(--el-muted);cursor:pointer}
.directory-card button.directory-icon-action:focus-visible{outline:2px solid var(--el-action);outline-offset:2px}
.directory-card button.directory-icon-action:hover{background:var(--el-panel)}
.directory-card-tier-free .directory-business-title{font-size:14px!important;line-height:1.25!important}
/* Free entries: single compact row, category conveyed by icon and accessible name. */
.directory-card.directory-card-tier-free .directory-card-body{padding:9px 12px!important}
.directory-card-tier-free .directory-compact-heading{align-items:center;gap:9px}
.directory-card.directory-card-tier-free .directory-business-title{margin:0!important;font-size:14px!important;line-height:1.3!important}
.directory-free-category-icon{display:grid;place-items:center;flex:0 0 28px;width:28px;height:28px;border-radius:7px;background:var(--el-panel);color:var(--el-muted)}
.directory-card-tier-free .directory-category-chip{padding:2px 5px!important;min-height:0!important;margin:0!important}
.directory-compact-heading{display:flex;gap:10px;align-items:flex-start;min-width:0}
.directory-card .directory-business-mark{position:static!important;width:38px!important;height:38px!important;min-width:38px;flex:0 0 38px;margin:0!important;border:1px solid var(--el-border);border-radius:9px;background:var(--el-panel);display:grid;place-items:center}
.directory-card .directory-business-mark img{width:100%;height:100%;max-height:38px;object-fit:contain;border-radius:8px}
.directory-heading-copy{min-width:0;flex:1}
.directory-card .directory-business-title{font-size:16px!important;line-height:1.3!important;margin:0 0 6px!important;min-height:0}
.directory-card .directory-business-title a,.directory-card .directory-business-title span{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-decoration:none}
.directory-card .directory-category-chip{cursor:pointer;display:inline-block;font-size:10px!important;line-height:1.35;letter-spacing:.2px!important;text-transform:none!important;padding:3px 7px;border:1px solid var(--el-border);border-radius:6px;background:var(--el-panel);color:var(--el-muted);margin:0 0 5px!important}
.directory-card .directory-icon-actions{display:flex;gap:3px;align-items:center;justify-content:flex-end;flex-wrap:wrap;margin-top:auto;padding-top:10px}
.directory-card a.directory-icon-action{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:0!important;margin:0!important;border:0!important;border-radius:9px;background:transparent;color:var(--el-action-hover);text-decoration:none}
.directory-card a.directory-icon-action:hover{background:var(--el-panel)}
.directory-card a.directory-icon-action:focus-visible{outline:2px solid var(--el-action);outline-offset:2px;background:var(--el-panel)}

body header,body footer,body.el-review-rest header,body.el-review-rest footer{background:var(--el-shell,#fff3da)!important;border-color:var(--el-shell-border,#d8b48a)!important}
body header{border-bottom:1px solid var(--el-shell-border,#d8b48a)!important}
body footer{border-top:1px solid var(--el-shell-border,#d8b48a)!important}
.home-editorial-gallery{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;grid-template-rows:auto 78px!important;gap:10px!important;position:relative!important;height:auto!important;min-height:0!important;aspect-ratio:auto!important;padding:0!important;margin:0!important;background:transparent!important;isolation:auto!important}
.home-editorial-gallery:before,.home-editorial-gallery:after,.home-gallery-accent{display:none!important}
.home-editorial-gallery .home-gallery-shot{position:relative!important;inset:auto!important;width:100%!important;height:100%!important;transform:none!important;clip-path:none!important;border-radius:12px!important;overflow:hidden!important;box-shadow:none!important;border:0!important;margin:0!important}
.home-editorial-gallery .home-gallery-shot-main{grid-column:1/-1!important;grid-row:1!important;aspect-ratio:3/2!important}
.home-editorial-gallery .home-gallery-shot-city,.home-editorial-gallery .home-gallery-shot-jerico,.home-editorial-gallery .home-gallery-shot-experience{grid-row:2!important;aspect-ratio:auto!important}
.home-editorial-gallery .home-gallery-shot img{display:block;width:100%!important;height:100%!important;object-fit:cover!important;transform:none!important;clip-path:none!important}
#formas-de-explorar .experience-stories h3{font-size:clamp(22px,2.5vw,28px)!important;line-height:1.2!important;color:#B84120!important;margin:8px 0 12px!important}
#formas-de-explorar .experience-stories .story-number{color:#B84120!important;font-weight:750!important;font-size:13px!important}
@media(max-width:650px){.home-editorial-gallery{grid-template-rows:auto 64px!important}}

.pilot-banner{display:none!important}
.row-load-more{display:block;margin:18px auto 0;min-height:44px;padding:10px 20px;border:1px solid var(--el-control-border);border-radius:8px;background:var(--el-card);color:var(--el-text);cursor:pointer}
.row-load-more[hidden]{display:none!important}
.highlight-grid>article[hidden],.destination-experience-list>article[hidden],.directory-grid>article[hidden]{display:none!important}
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
 density_path=ROOT/'backend/public/assets/densidad-global.css'
 if density_path.is_file():css+='\n'+density_path.read_text()
 (assets/'identidad-neutral.css').write_text(css)
 shutil.copy2(ROOT/'backend/public/assets/identidad-neutral.js',assets/'identidad-neutral.js') if assets.resolve()!=(ROOT/'backend/public/assets').resolve() else None
 shutil.copy2(ROOT/'backend/public/assets/tarjetas-negocios-compactas.js',assets/'tarjetas-negocios-compactas.js') if assets.resolve()!=(ROOT/'backend/public/assets').resolve() else None
 shutil.copy2(ROOT/'backend/public/assets/cargar-mas-tarjetas.js',assets/'cargar-mas-tarjetas.js') if assets.resolve()!=(ROOT/'backend/public/assets').resolve() else None
 # Replace only known former surface/border colours. Preserve map/status colours,
 # illustration SVGs, logos and photographic pixels.
 palette={'#fffaf2':'#fafafa','#fff0dc':'#f1f2f4','#fff8ed':'#ffffff','#fffdf8':'#ffffff','#ffe4ba':'#e9ebee','#f2c77e':'#dadde2','#e9b75f':'#bdc2cb','#fff3dc':'#f1f2f4','#fff4df':'#f1f2f4','#fff7e8':'#ffffff','#fff5df':'#f1f2f4','#fff9ec':'#ffffff','#f0d39a':'#dadde2','#e8efeb':'#f1f2f4','#dfe9e3':'#e9ebee','#d7e0da':'#dadde2','#bacac1':'#bdc2cb','#faf9f5':'#fafafa','#f5b126':'#bdc2cb'}
 css_count=0
 for p in assets.rglob('*.css'):
  if p.name in {'leaflet.css','identidad-neutral.css'}:continue
  s=p.read_text();new=re.sub('|'.join(palette),lambda m:palette[m[0].lower()],s,flags=re.I)
  if new!=s:p.write_text(new);css_count+=1
 hook='<link rel="stylesheet" href="/assets/identidad-neutral.css?v=5">'
 count=0
 for p in public.rglob('*'):
  if p.suffix not in {'.html','.php'} or p.name.startswith('_'):continue
  s=p.read_text()
  s=s.replace('favicon.svg','brand/explorando-lugares-logo-v2-transparent.png').replace('type="image/svg+xml"','type="image/png"')
  if '</head>' not in s:continue
  s=re.sub(r'<!-- etapas-piloto -->.*?<!-- /etapas-piloto -->','',s,flags=re.S)
  s=re.sub(r'<aside\b[^>]*class="pilot-banner"[^>]*>.*?</aside>','',s,flags=re.S)
  for old,new in {'Comenzamos por Jericó.':'Destinos para descubrir.','La primera guía ya está disponible. Abriremos otros destinos cuando revisemos su información y sus imágenes.':'Explora las guías disponibles.','Começamos por Jericó.':'Destinos para descobrir.','We start with Jericó.':'Destinations to discover.','Starting with Jericó.':'Destinations to discover.'}.items():s=s.replace(old,new)
  s=s.replace('identidad-neutral.css?v=4','identidad-neutral.css?v=5')
  s=s.replace('identidad-neutral.css?v=3','identidad-neutral.css?v=5')
  s=s.replace('identidad-neutral.js?v=3','identidad-neutral.js?v=4')
  s=s.replace('identidad-neutral.js?v=2','identidad-neutral.js?v=4').replace('identidad-neutral.css?v=2','identidad-neutral.css?v=5')
  s=s.replace('tarjetas-negocios-compactas.js?v=1','tarjetas-negocios-compactas.js?v=4')
  s=s.replace('tarjetas-negocios-compactas.js?v=2','tarjetas-negocios-compactas.js?v=4')
  s=s.replace('tarjetas-negocios-compactas.js?v=3','tarjetas-negocios-compactas.js?v=4')
  if 'tarjetas-negocios-compactas.js' not in s:s=s.replace('</head>','<script defer src="/assets/tarjetas-negocios-compactas.js?v=4"></script></head>',1)
  if 'cargar-mas-tarjetas.js' not in s:s=s.replace('</head>','<script defer src="/assets/cargar-mas-tarjetas.js?v=1"></script></head>',1)
  s=re.sub(r'<a\b[^>]*class="text-link"[^>]*href="[^"]*jerico[^"]*"[^>]*>.*?</a>','',s) if 'home-editorial-actions' in s else s
  if 'identidad-neutral.css' not in s:s=s.replace('</head>',hook+'</head>',1)
  if s!=p.read_text():p.write_text(s);count+=1
 print(json.dumps({'public':str(public),'updated_pages':count,'updated_stylesheets':css_count,'published':False}))
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);a=p.parse_args();apply(a.public)
