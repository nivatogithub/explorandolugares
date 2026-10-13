#!/usr/bin/env python3
"""Inserta el ícono de Explorando Lugares en todas las páginas estáticas."""
from pathlib import Path
import os
import re
import hashlib

ROOT=Path(__file__).resolve().parents[1]
SITE=ROOT/'ensayos/v1'
STYLE_VERSION=hashlib.sha256((SITE/'assets/style.css').read_bytes()).hexdigest()[:12]

for page in SITE.rglob('*.html'):
    source=page.read_text()
    has_head='</head>' in source
    href=Path(os.path.relpath(SITE/'assets/favicon.svg',page.parent)).as_posix()
    source=re.sub(r'\s*<link\s+rel="(?:icon|shortcut icon)"[^>]*>','',source,flags=re.I)
    source=re.sub(r'(href="[^"]*style\.css)(?:\?v=[^"]*)?(")',r'\1?v='+STYLE_VERSION+r'\2',source)
    icon=f'<link rel="icon" href="{href}" type="image/svg+xml">'
    if has_head:
        source=source.replace('</head>',icon+'</head>',1)
    else:
        source=source.replace('<title>',icon+'<title>',1)
    page.write_text(source)
