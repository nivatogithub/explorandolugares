#!/usr/bin/env python3
"""Construye una entrega PHP fuera de los históricos, sin copiar secretos."""
from pathlib import Path
import shutil
import argparse
import subprocess
import sys
import hashlib
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output',type=Path,default=root/'.local/hosting')
args=parser.parse_args()
subprocess.run([sys.executable,str(root/'scripts/generar-opciones-contacto.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-destinos.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-fichas.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-ingles.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/agregar-favicon.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-seo.py')],check=True)
release=args.output.resolve()
public=release/'public'
public.mkdir(parents=True,exist_ok=True)
# Retirar fichas generadas que fueron despublicadas, también del paquete local.
for page in public.rglob('index.html'):
    if not (root/'ensayos/v1'/page.relative_to(public)).is_file() and '<!-- ficha-generada-explorando -->' in page.read_text():
        page.unlink()
for src in (root/'ensayos/v1').rglob('*'):
    if src.is_file() and src.suffix in {'.html','.css','.js','.json','.svg','.jpg','.png','.txt','.xml'}:
        target=public/src.relative_to(root/'ensayos/v1');target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(src,target)
shutil.copytree(root/'backend/public',public,dirs_exist_ok=True)
# El hosting conserva CSS durante varios días. Versionar la URL en cada entrega
# evita que una página HTML nueva use una hoja de estilos anterior desde caché.
style=public/'assets/style.css'
if not style.is_file(): raise RuntimeError('Falta assets/style.css en la entrega pública.')
style_version=hashlib.sha256(style.read_bytes()).hexdigest()[:12]
for page in public.rglob('*.html'):
    content=page.read_text()
    content=content.replace('style.css"',f'style.css?v={style_version}"')
    page.write_text(content)
shutil.copytree(root/'backend/app',release/'app',dirs_exist_ok=True)
shutil.copytree(root/'backend/database',release/'database',dirs_exist_ok=True)
shutil.copytree(root/'backend/bin',release/'bin',dirs_exist_ok=True)
shutil.copyfile(root/'backend/README.md',release/'INSTALACION.md')
(release/'private').mkdir(exist_ok=True)
shutil.copyfile(root/'backend/config.example.php',release/'config.example.php')
print(f'Entrega de revisión: {release}\nRaíz pública: {public}\nConfigurar private/config.php fuera de public/. No hay publicación automática.')
