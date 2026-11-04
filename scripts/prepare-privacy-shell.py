#!/usr/bin/env python3
"""Build private static shell fragments around one explicit privacy component."""
from pathlib import Path
import argparse

parser=argparse.ArgumentParser()
parser.add_argument('--release',type=Path,required=True)
release=parser.parse_args().release
marker='<div data-privacy-info></div>'
output=release/'app/presentation/privacy-shell'
output.mkdir(parents=True,exist_ok=True)
for prefix,locale in (('', 'es'),('en/', 'en'),('pt-br/', 'pt-br')):
    source=(release/'public'/prefix/'privacidad/index.html').read_text()
    if source.count(marker)!=1:
        raise ValueError('La página de privacidad debe contener un único componente: '+locale)
    before,after=source.split(marker)
    (output/(locale+'-before.html')).write_text(before)
    (output/(locale+'-after.html')).write_text(after)
