#!/usr/bin/env python3
"""Audita los textos entregados en PT-BR y detecta residuos visibles de español."""
from pathlib import Path
from html.parser import HTMLParser
import re
ROOT=Path(__file__).resolve().parents[1]; SITE=ROOT/'ensayos/v1/pt-br'
# Señales que no pertenecen al portugués brasileño. Los nombres geográficos y
# títulos originales de fotos se excluyen porque son atribuciones, no interfaz.
spanish=re.compile(r'[¿¡ñ]|\b(qué|dónde|aquí|también|pueblo|correo|teléfono|selecciona|siguiente|conoce|recarga|envía|ciudad moderna|para conocer|no hay|aún no|con tus|tu negocio)\b',re.I)
proper=re.compile(r'^(?:El Retiro|El Peñol|El Carmen de Viboral|Santa Fe de Antioquia|San Jerónimo|San Rafael|San Carlos|San Pedro de los Milagros|Santa Rosa de Osos|Puerto Triunfo|San Antonio de Pereira|Cerro Cristo Rey|Parque Simón Bolívar|Parque El Libertador|Costa Caribe|Cultura local|Paisajes fluviales)$')
class Reader(HTMLParser):
 def __init__(self):super().__init__();self.items=[]
 def add(self,v):
  v=re.sub(r'\s+',' ',v or '').strip()
  if v:self.items.append(v)
 def handle_data(self,data):self.add(data)
 def handle_starttag(self,tag,attrs):
  for k,v in attrs:
   if k in {'alt','aria-label','placeholder','content','title'}:self.add(v)
issues=[]
for page in SITE.rglob('*.html'):
 # Créditos preservan títulos originales de fotografías y licencias.
 if 'creditos-fotograficos' in page.parts:continue
 r=Reader();r.feed(page.read_text())
 for text in r.items:
  clean=re.sub(r'(?:El Peñol|Peñol|Guatapé|Javiera Londoño)', '', text)
  if spanish.search(clean) and not proper.fullmatch(text):issues.append((page.relative_to(SITE),text))
if issues:
 for page,text in issues:print(f'{page}: {text}')
 raise SystemExit(1)
print(f'OK: auditoría PT-BR aprobada en {sum(1 for _ in SITE.rglob("*.html"))} páginas públicas y de preparación; los créditos conservan títulos y licencias originales.')
