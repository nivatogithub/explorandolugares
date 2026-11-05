#!/usr/bin/env python3
"""Generate a scoped Apache mapping for newly imported IDs, without replacing existing mappings."""
import json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
receipt=json.loads((root/'dev1-place-receipt.json').read_text())
assert receipt['environment']=='dev1' and len(receipt['places'])==3
ids=[str(int(p['id'])) for p in receipt['places']];assert len(set(ids))==3
base='/srv/state/place-packages-30/media'
text='AliasMatch "^/assets/destinos/('+ '|'.join(ids)+')/(.*)$" "'+base+'/assets/destinos/$1/$2"\n<Directory '+base+'>\n Require all granted\n Options -Indexes\n</Directory>\n'
(root/'dev1-media-alias.conf').write_text(text)
print('Scoped media mapping for',len(ids),'place IDs')
