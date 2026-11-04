#!/usr/bin/env python3
"""Build private page fragments around explicit subscription components."""
from pathlib import Path
import argparse

parser=argparse.ArgumentParser()
parser.add_argument('--release',type=Path,required=True)
release=parser.parse_args().release
output=release/'app/presentation/commercial-shell'
output.mkdir(parents=True,exist_ok=True)
for prefix,locale in (('', 'es'),('en/', 'en'),('pt-br/', 'pt-br')):
    for section,kind in (('suscripciones','subscriptions'),('proceso','business-process')):
        text=(release/'public'/prefix/'negocios-locales'/section/'index.html').read_text()
        markers=(['<div data-subscription-pricing></div>'] if kind=='subscriptions' else [])+['<p data-operator-pricing></p>']
        for index,marker in enumerate(markers):
            if text.count(marker)!=1:raise ValueError('Componente de tarifa ausente o duplicado: '+locale+' '+kind)
            before,text=text.split(marker)
            (output/(locale+'-'+kind+'-'+str(index)+'.html')).write_text(before)
        (output/(locale+'-'+kind+'-'+str(len(markers))+'.html')).write_text(text)
