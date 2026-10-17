#!/usr/bin/env python3
"""Build an allowlisted database change package; never execute SQL."""
import argparse,hashlib,json,re,zipfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser(description=__doc__);p.add_argument('--output',type=Path,required=True);p.add_argument('ids',nargs='+');a=p.parse_args()
source=(root/'backend/app/DatabaseMaintenance.php').read_text()
catalog=dict(re.findall(r"'([0-9]{3}-[a-z0-9-]+)'\s*=>\s*\[\s*'file'\s*=>\s*'([^']+)'",source))
if len(set(a.ids))!=len(a.ids) or any(i not in catalog for i in a.ids):p.error('IDs desconocidos o repetidos')
ids=[i for i in catalog if i in a.ids];items=[];files={}
for i in ids:
    name=catalog[i];data=(root/'backend/database/migrations'/name).read_bytes();files[name]=data;items.append(dict(id=i,file=name,sha256=hashlib.sha256(data).hexdigest()))
a.output.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(a.output,'w',zipfile.ZIP_DEFLATED) as z:
    z.writestr('manifest.json',json.dumps(dict(format='explorandolugares-db-v1',engine='mysql-mariadb',migrations=items),indent=2))
    for name,data in files.items():z.writestr(name,data)
print(f'Paquete generado: {a.output} ({len(ids)} migraciones). Sin ejecución.')
