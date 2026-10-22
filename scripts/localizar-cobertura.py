#!/usr/bin/env python3
"""Build translated coverage pages and interaction copy; never translate browser DOM after loading."""
from pathlib import Path
from html.parser import HTMLParser
from html import escape
from urllib.parse import urljoin,urlsplit
import argparse,json,re
ROOT=Path(__file__).resolve().parents[1]
COPY=json.loads((ROOT/'backend/app/presentation/coverage.json').read_text())
class Page(HTMLParser):
 def __init__(self,dictionary,prefix):super().__init__(convert_charrefs=True);self.dictionary=dictionary;self.prefix=prefix;self.output=[];self.skip=0
 def translate(self,text):
  key=text.strip();value=self.dictionary.get(key)
  if value is None and key.endswith(' | Explorando Lugares'):value=self.dictionary.get(key[:-len(' | Explorando Lugares')],key[:-len(' | Explorando Lugares')])+' | Explorando Lugares'
  return text.replace(key,value,1) if key and value is not None else text
 def handle_decl(self,value):self.output.append('<!'+value+'>')
 def handle_starttag(self,tag,attrs):
  if tag in ['script','style']:self.skip+=1
  values=[]
  for name,value in attrs:
   if value is None:values.append(name);continue
   if name in ['title','aria-label','placeholder','alt'] or (tag=='meta' and name=='content' and any(k=='name' and v=='description' for k,v in attrs)):value=self.translate(value)
   if name=='lang' and tag=='html':value='en' if self.prefix=='en' else 'pt-BR'
   if name in ['href','src'] and value and not value.startswith(('#','mailto:','tel:','https://','http://')):
    value=urljoin('/cobertura/colombia/',value)
    if name=='href' and not value.startswith(('/assets/','/api/')):value='/'+self.prefix+value
   if name=='href' and value=='https://explorandolugares.com/cobertura/colombia/':value='https://explorandolugares.com/'+self.prefix+'/cobertura/colombia/'
   if name=='src' and 'assets/cobertura/cobertura-meta.js' in value:value='/assets/cobertura/cobertura-meta-'+self.prefix+'.js'
   values.append(name+'="'+escape(value,quote=True)+'"')
  self.output.append('<'+tag+(' '+' '.join(values) if values else '')+'>')
 def handle_endtag(self,tag):
  self.output.append('</'+tag+'>')
  if tag in ['script','style']:self.skip-=1
 def handle_data(self,text):self.output.append(text if self.skip else escape(self.translate(text),quote=False))
 def handle_comment(self,text):self.output.append('<!--'+text+'-->')
def javascript(source,column):
 mapping={key:values[column] for key,values in COPY.items()}
 def replace(match):
  quote,body=match[1],match[2]
  if body in mapping:body=mapping[body]
  elif quote=='`':
   for key in sorted(mapping,key=len,reverse=True):
    if len(key)>4:body=body.replace(key,mapping[key])
  if body=='es-CO':body='en-US' if column==0 else 'pt-BR'
  elif body=='es':body='en' if column==0 else 'pt-BR'
  if quote in ['"',"'"]:body=body.replace(quote,'\\'+quote)
  return quote+body+quote
 return re.sub(r"(['\"`])((?:\\.|(?!\1).)*)\1",replace,source)
def apply(public):
 source=(public/'cobertura/colombia/index.html').read_text();js=(public/'assets/cobertura/cobertura-meta.js').read_text()
 for column,prefix in enumerate(['en','pt-br']):
  dictionary=json.loads((ROOT/'content'/('en.json' if prefix=='en' else 'pt-br.json')).read_text());dictionary.update({k:v[column] for k,v in COPY.items()})
  page=Page(dictionary,prefix);page.feed(source);target=public/prefix/'cobertura/colombia/index.html';target.parent.mkdir(parents=True,exist_ok=True);target.write_text(''.join(page.output))
  (public/'assets/cobertura'/('cobertura-meta-'+prefix+'.js')).write_text(javascript(js,column))
 print('Cobertura: páginas y textos de interacción en español, inglés y portugués.')
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--public',type=Path,required=True);apply(parser.parse_args().public)
