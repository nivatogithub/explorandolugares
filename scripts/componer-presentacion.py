#!/usr/bin/env python3
"""Compose all page shells from the same PHP components before publication."""
from pathlib import Path
import argparse, json, re, subprocess
from urllib.parse import urlsplit
ROOT=Path(__file__).resolve().parents[1]
RETIRED=['identidad-neutral.js','bogota-compacta.js','cartagena-compacta.js','medellin-compacta.js','santa-marta-compacta.js','destino-lectura-directa.js','nomenclatura-lugares.js','language-navigation.js','portada-atracciones.js','portada.js']
SHELL=re.compile(r'<header\b[^>]*>.*?</header>',re.S)
FOOTER=re.compile(r'<footer\b[^>]*>.*?</footer>',re.S)
MODULES=json.loads((ROOT/'backend/app/presentation/resources.json').read_text())
RESOURCES=[(kind,name) for kind,names in MODULES['common'].items() for name in names]

def context(path,public):
 rel=path.relative_to(public).as_posix();locale='es'
 canonical=re.search(r'<link\b[^>]*rel="canonical"[^>]*href="([^"]+)"',path.read_text())
 if canonical:
  candidate=urlsplit(canonical[1]).path.lstrip('/')
  if candidate.endswith('/'):candidate+='index.html'
  if (public/candidate).is_file():rel=candidate
 for prefix,lang in [('en/','en'),('pt-br/','pt-BR')]:
  if rel.startswith(prefix):rel=rel[len(prefix):];locale=lang;break
 route='/'+rel.removesuffix('index.html')
 languages={}
 for lang,prefix in [('es',''),('en','en/'),('pt-BR','pt-br/')]:
  if (public/prefix/rel).is_file():languages[lang]='/'+prefix+rel.removesuffix('index.html')
 return {'locale':locale,'path':route,'languages':languages}
def resources(text, private_preview=False):
 text=re.sub(r'<link\b[^>]*href="[^"]*assets/header-controls\.css(?:\?[^"]*)?"[^>]*>','',text)
 css=[];scripts=[]
 for kind,name in RESOURCES:
  text=re.sub(r'<(?:script|link)\b[^>]*(?:src|href)=["\x27][^"\x27]*assets/'+re.escape(name)+r'(?:\?[^"\x27]*)?["\x27][^>]*>(?:</script>)?','',text)
  url='/assets/'+name
  if kind=='css':css.append(f'<link rel="stylesheet" href="{url}">')
  else:
   tag=f'<script defer src="{url}"'+(' data-el-measurement="1"' if name.endswith('medicion.js') else '')+'></script>'
   if private_preview and name.endswith('medicion.js'):tag="<?php if(!isset($elBusinessDemoPlan)&&!isset($elEditorialPreviewBusiness)):?>"+tag+"<?php endif ?>"
   scripts.append(tag)
 first=re.search(r'<link\b[^>]*rel="stylesheet"',text)
 position=first.start() if first else text.find('</head>')
 if position>=0:text=text[:position]+''.join(css)+text[position:]
 return text.replace('</head>',''.join(scripts)+'</head>',1)

def apply(public):
 public=Path(public);pages={}
 for path in sorted(public.rglob('*.html')):
  text=path.read_text()
  if SHELL.search(text):pages[path.relative_to(public).as_posix()]=context(path,public)
 result=subprocess.run(['php',str(ROOT/'scripts/renderizar-shell.php')],input=json.dumps(pages),text=True,capture_output=True,check=True)
 rendered=json.loads(result.stdout)
 for relative,components in rendered.items():
  path=public/relative;text=path.read_text();text=SHELL.sub(lambda m:components['header'],text,count=1);text=FOOTER.sub(lambda m:components['footer'],text,count=1)
  text=resources(text)
  languages=pages[relative]['languages']
  text=re.sub(r'<link\b(?=[^>]*\brel=[\"\x27]alternate[\"\x27])(?=[^>]*\bhreflang=)[^>]*>','',text)
  if len(languages)>1:
   links=''.join('<link rel="alternate" hreflang="'+lang+'" href="https://explorandolugares.com'+url+'">' for lang,url in languages.items())
   if 'es' in languages:links+='<link rel="alternate" hreflang="x-default" href="https://explorandolugares.com'+languages['es']+'">'
   text=text.replace('</head>',links+'</head>',1)
  path.write_text(text)
 for path in public.rglob('*.php'):
  if path.name.startswith('_'):continue
  if path.relative_to(public).parts[0]=='admin':continue
  text=path.read_text()
  if not SHELL.search(text):continue
  depth=len(path.relative_to(public).parts)-1
  require="__DIR__.'/_portal-shell.php'" if depth==0 else f"dirname(__DIR__,{depth}).'/_portal-shell.php'"
  ctx="['locale'=>($locale??$_GET['lang']??'es')]"
  if path.name=='destino.php':ctx="['locale'=>$locale,'path'=>'/lugares/'.$slug.'/','languages'=>['es'=>$langEsUrl,'en'=>$langEnUrl,'pt-BR'=>$langPtUrl]]"
  text=SHELL.sub(lambda m:f'<?php require_once {require}; echo PortalPresentation::header({ctx}); ?>',text,count=1)
  footer=f'<?=PortalPresentation::footer({ctx})?>'
  if FOOTER.search(text):text=FOOTER.sub(lambda m:footer,text,count=1)
  else:text=text.replace('</body>',footer+'</body>',1)
  text=resources(text,private_preview=path.name=='negocio.php');path.write_text(text)
 for path in public.rglob('*'):
  if not path.is_file() or path.suffix not in {'.html','.php'}:continue
  text=path.read_text()
  if not any(marker in text for marker in ['destination-main.php','directory-card.php','directory-grid','highlight-grid','destination-experience-list']):
   for name in ['tarjetas-negocios-compactas.js','cargar-mas-tarjetas.js']:text=re.sub(r'<script\b[^>]*src="[^"]*'+re.escape(name)+r'(?:\?[^"]*)?"[^>]*></script>','',text)
  for name in RETIRED:text=re.sub(r'<script\b[^>]*src="[^"]*'+re.escape(name)+r'(?:\?[^"]*)?"[^>]*></script>','',text)
  path.write_text(text)
 print(f'Composición única: {len(pages)} páginas estáticas y shell PHP compartido')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--public',type=Path,required=True);apply(p.parse_args().public)
