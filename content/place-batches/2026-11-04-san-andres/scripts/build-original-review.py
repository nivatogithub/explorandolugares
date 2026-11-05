#!/usr/bin/env python3
"""Rebuild standalone original-photo review; no network or image transformation."""
from pathlib import Path
import base64, mimetypes, re
root = Path(__file__).resolve().parents[1]
refs = root / 'places/san-andres/references'
template = (root / 'scripts/revision-originales.template.html').read_text()
def embed(match):
    file = refs / match.group(1)
    return 'data:' + mimetypes.guess_type(file.name)[0] + ';base64,' + base64.b64encode(file.read_bytes()).decode()
output = re.sub(r'\{\{IMAGE:([^}]+)\}\}', embed, template)
assert output.count('src="data:image/') == 8
(root / 'revision-originales.html').write_text(output)
print('8 original images embedded in revision-originales.html')
