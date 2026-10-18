#!/usr/bin/env python3
"""Vistas privadas locales, exclusivamente entregas de cuatro workers; sin Chrome ni producción."""
from http.server import ThreadingHTTPServer,BaseHTTPRequestHandler
from pathlib import Path
from urllib.parse import urlsplit,unquote,quote
import argparse,html,json,mimetypes,threading,urllib.request,subprocess
ROOT=Path(__file__).resolve().parents[1]
DESTINATIONS={
 'cartagena':('Cartagena',1,'preview.html'),
 'bogota':('Bogotá',2,'previsualizacion.html'),
 'medellin':('Medellín',3,'preview.html'),
 'santa-marta':('Santa Marta',4,'index.html')}
ALLOWED={'.html','.css','.js','.png','.jpg','.jpeg','.webp','.svg','.json','.md','.txt','.woff','.woff2'}
def folder(slug):return ROOT.parent/f'worker-lugar-{DESTINATIONS[slug][1]}'/'crecimiento-colombia/entregas'/slug
class Handler(BaseHTTPRequestHandler):
 def log_message(self,fmt,*args):pass
 def do_HEAD(self):self.send_page(False)
 def do_GET(self):self.send_page(True)
 def do_POST(self):self.send_error(405,'Solo lectura')
 def send_page(self,body):
  path=unquote(urlsplit(self.path).path)
  if path=='/':
   cards=[]
   for slug,(name,n,entry) in DESTINATIONS.items():
    commit=subprocess.check_output(['git','-C',str(folder(slug).parents[2]),'rev-parse','--short','HEAD'],text=True).strip()
    cards.append(f'<article><h2><a href="/{slug}/">{name}</a></h2><p>Lugar {n} · versión {html.escape(commit)}</p><p><a href="/{slug}/">Abrir textos y fotos →</a> · <a href="/{slug}/STATUS.md">Pendientes de la entrega</a></p></article>')
   content=('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Revisión local de destinos</title><style>body{font:18px/1.6 system-ui;background:#fffaf2;color:#172b42;margin:0}main{max-width:1000px;margin:auto;padding:24px}article{background:#fff8ed;padding:22px;border:1px solid #f2c77e;border-radius:14px;margin:18px 0}a{color:#a83b22}h1{line-height:1.2}</style><main><h1>Revisión local de destinos</h1><p>Borradores privados. Fotos, textos y traducciones pendientes de aprobación; ningún destino está publicado por esta revisión.</p>'+''.join(cards)+'<p>Indica destino y foto o sección en el chat para solicitar correcciones. Esta ventana solo permite consultar.</p></main></html>').encode();kind='text/html; charset=utf-8'
  else:
   parts=path.strip('/').split('/');slug=parts[0]
   if slug not in DESTINATIONS or any(p in ('..','.') or p.startswith('.') for p in parts):return self.send_error(404)
   base=folder(slug).resolve();relative='/'.join(parts[1:]) or DESTINATIONS[slug][2];target=(base/relative).resolve()
   if not target.is_relative_to(base) or not target.is_file() or target.suffix.lower() not in ALLOWED:return self.send_error(404)
   content=target.read_bytes();kind=mimetypes.guess_type(target.name)[0] or 'application/octet-stream'
   if target.suffix in ('.html','.css','.js','.md','.txt'):kind+='; charset=utf-8'
  self.send_response(200);self.send_header('Content-Type',kind);self.send_header('Content-Length',str(len(content)));self.send_header('Cache-Control','no-store');self.send_header('X-Robots-Tag','noindex, nofollow');self.send_header('X-Content-Type-Options','nosniff');self.end_headers()
  if body:self.wfile.write(content)
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--port',type=int,default=8061);args=parser.parse_args()
 server=ThreadingHTTPServer(('127.0.0.1',args.port),Handler);threading.Thread(target=server.serve_forever,daemon=True).start()
 origin=f'http://127.0.0.1:{args.port}'
 from html.parser import HTMLParser
 from urllib.parse import urljoin
 class Images(HTMLParser):
  def __init__(self):super().__init__();self.paths=[]
  def handle_starttag(self,tag,attrs):
   if tag=='img':self.paths += [v for k,v in attrs if k=='src']
 for slug in ['',*DESTINATIONS]:
  url=origin+'/'+(slug+'/' if slug else '')
  with urllib.request.urlopen(url,timeout=10) as response:data=response.read().decode()
  images=Images();images.feed(data)
  for path in images.paths:
   image=urljoin(url,path)
   if urlsplit(image).netloc==urlsplit(origin).netloc:
    with urllib.request.urlopen(image,timeout=10) as response:assert response.status==200
  print('OK',url,'· imágenes locales',len(images.paths),flush=True)
 print('Servidor local activo; solo lectura. Ctrl+C para detener.',flush=True)
 try:threading.Event().wait()
 except KeyboardInterrupt:server.shutdown()
