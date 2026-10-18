#!/usr/bin/env python3
"""Checks visible prepared HTML copy without counting routes or internal identifiers."""
from pathlib import Path
from html.parser import HTMLParser
import re,subprocess
ROOT=Path(__file__).resolve().parents[1];PUBLIC=ROOT/'.local/preprod/release/public'
class Visible(HTMLParser):
 def __init__(self):super().__init__();self.skip=0;self.hits=[]
 def check(self,text):
  if re.search(r'(?<![\w/.-])(?:destinos?|destinations?)(?![\w/-]|\.[a-z])',text,re.I):self.hits.append(text.strip()[:180])
 def handle_starttag(self,tag,attrs):
  if tag in ['script','style','pre','code']:self.skip+=1
  for key,value in attrs:
   if key in ['aria-label','title','alt','placeholder'] and value:self.check(value)
 def handle_endtag(self,tag):
  if tag in ['script','style','pre','code']:self.skip=max(0,self.skip-1)
 def handle_data(self,text):
  if not self.skip:self.check(text)
fail=[];pages=list(PUBLIC.rglob('*.html'))
for path in pages:
 p=Visible();p.feed(path.read_text())
 if p.hits:fail.append((str(path.relative_to(PUBLIC)),p.hits[:2]))
if fail:raise SystemExit(str(fail))
for root in [PUBLIC,ROOT/'.local/preprod/release/app']:
 for path in root.rglob('*.php'):
  p=subprocess.run(['php','-l',str(path)],capture_output=True,text=True)
  if p.returncode:raise SystemExit(p.stdout+p.stderr)
print(f'OK: {len(pages)} páginas HTML sin destino/destination en texto visible o etiquetas accesibles; PHP preparado válido.')
