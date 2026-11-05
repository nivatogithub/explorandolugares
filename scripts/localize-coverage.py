#!/usr/bin/env python3
"""Build localized interaction resources for the coverage component."""
from pathlib import Path
import argparse,json,re
ROOT=Path(__file__).resolve().parents[1]
COPY=json.loads((ROOT/'backend/app/presentation/coverage.json').read_text())
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
 public=Path(public)
 js=(public/'assets/cobertura/coverage-meta.js').read_text()
 for column,prefix in enumerate(['en','pt-br']):
  (public/'assets/cobertura'/('cobertura-meta-'+prefix+'.js')).write_text(javascript(js,column))
 print('Cobertura: recursos de interacción en inglés y portugués.')
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--public',type=Path,required=True);apply(parser.parse_args().public)
