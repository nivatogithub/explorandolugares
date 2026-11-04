#!/usr/bin/env python3
"""Build private home fragments around the explicit attractions component."""
from pathlib import Path
import argparse

parser=argparse.ArgumentParser()
parser.add_argument('--release',type=Path,required=True)
release=parser.parse_args().release
output=release/'app/presentation/home-shell'
output.mkdir(parents=True,exist_ok=True)
marker='<div class="home-attractions" data-home-attractions>'
for prefix,locale in (('', 'es'),('en/', 'en'),('pt-br/', 'pt-br')):
    text=(release/'public'/prefix/'index.html').read_text()
    if text.count(marker)!=1:raise ValueError('La portada debe contener un único componente: '+locale)
    before,rest=text.split(marker)
    fallback,separator,after=rest.partition('</div>')
    if not separator or '<div' in fallback or not fallback.strip().startswith('<p>') or not fallback.strip().endswith('</p>'):
        raise ValueError('El contenido de respaldo de portada no es el esperado: '+locale)
    for name,value in (('before',before),('fallback',fallback),('after',after)):
        (output/(locale+'-'+name+'.html')).write_text(value)
