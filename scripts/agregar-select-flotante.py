#!/usr/bin/env python3
"""Incluye el selector flotante accesible en todas las páginas estáticas."""
from pathlib import Path
import os
import re

ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / 'ensayos/v1'

for page in SITE.rglob('*.html'):
    source = page.read_text()
    if '</body>' not in source:
        continue
    source = re.sub(r'\s*<script src="[^"]*assets/select-flotante\.js" defer></script>', '', source)
    asset = Path(os.path.relpath(SITE / 'assets/select-flotante.js', page.parent)).as_posix()
    source = source.replace('</body>', f'<script src="{asset}" defer></script></body>', 1)
    page.write_text(source)
