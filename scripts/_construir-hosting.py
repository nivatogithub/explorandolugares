#!/usr/bin/env python3
"""Construye una entrega PHP fuera de los históricos, sin copiar secretos."""
from pathlib import Path
import shutil
import argparse
import subprocess
import sys
import hashlib
import json
import re
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output',type=Path,default=root/'.local/hosting')
args=parser.parse_args()
subprocess.run([sys.executable,str(root/'scripts/generar-opciones-contacto.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-destinos.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-fichas.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-aviso-etapas.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-ingles.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-creditos.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-portugues.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/agregar-select-flotante.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/normalizar-flechas-enlaces.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/agregar-favicon.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-seo.py')],check=True)
subprocess.run([sys.executable,str(root/'scripts/agregar-contacto-footer.py')],check=True)
release=args.output.resolve()
public=release/'public'
public.mkdir(parents=True,exist_ok=True)
from public_inputs import collect
collect(root,public)
subprocess.run([sys.executable,str(root/'scripts/localizar-cobertura.py'),'--public',str(public)],check=True)
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
shutil.copytree(root/'backend/bin',release/'bin',dirs_exist_ok=True)
content=release/'content';content.mkdir(exist_ok=True)
shutil.copyfile(root/'content/destinos-dinamicos.json',content/'destinos-dinamicos.json')
# El importador inicial queda privado junto al backend y a su catálogo semilla.
# La configuración real y los SQL no se copian a la entrega.
private_tools=release/'app/bin';private_tools.mkdir(parents=True,exist_ok=True)
shutil.copyfile(root/'backend/bin/importar-destinos.php',private_tools/'importar-destinos.php')
shutil.copyfile(root/'backend/bin/importar-negocios.php',private_tools/'importar-negocios.php')
private_content=release/'app/content';private_content.mkdir(parents=True,exist_ok=True)
shutil.copyfile(root/'content/release-places-businesses.json',private_content/'release-places-businesses.json')
shutil.copyfile(root/'content/destinos-dinamicos.json',private_content/'destinos-dinamicos.json')
shutil.copyfile(root/'content/plantilla-importar-negocios.json',private_content/'plantilla-importar-negocios.json')
shutil.copyfile(root/'content/plantilla-paquete-negocios.zip',private_content/'plantilla-paquete-negocios.zip')
shutil.copytree(root/'backend/database',release/'app/database',dirs_exist_ok=True)
shutil.copyfile(root/'backend/README.md',release/'INSTALACION.md')
(release/'private').mkdir(exist_ok=True)
shutil.copyfile(root/'backend/config.example.php',release/'config.example.php')
subprocess.run([
    sys.executable,str(root/'scripts/limpiar-paquete-publico.py'),
    '--public',str(public),'--catalog',str(private_content/'destinos-dinamicos.json'),
    '--app',str(release/'app')
],check=True)
for obsolete in ['logo.svg','logo-completo.svg','favicon.svg']:
    (public/'assets'/obsolete).unlink(missing_ok=True)
subprocess.run([sys.executable,str(root/'scripts/aplicar-identidad-neutral.py'),'--public',str(public)],check=True)
subprocess.run(['php',str(root/'scripts/aplicar-nomenclatura-lugares.php'),str(release)],check=True)
subprocess.run([sys.executable,str(root/'scripts/ajustar-auditoria-publica.py'),'--public',str(public)],check=True)
# Stamp all generated pages after the integration overlay, including general pages.
subprocess.run([sys.executable,str(root/'scripts/versiones-medicion.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/normalizar-ruta-suscripciones.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-sitemap-entrega.py'),'--public',str(public)],check=True)
# Approved destination media are data-bound: copy them AFTER legacy pruning/overlays.
# A successful build must reproduce the exact reviewed bytes, never an old alias.
approved_media=json.loads((root/'backend/app/content/release-media-manifest.json').read_text())
for item in approved_media['media']:
    relative=Path(item['path'])
    if relative.is_absolute() or '..' in relative.parts or relative.parts[0]!='assets':
        raise RuntimeError('Ruta de medio aprobado inválida')
    source=root/'backend/public'/relative
    if not source.is_file() or hashlib.sha256(source.read_bytes()).hexdigest()!=item['sha256']:
        raise RuntimeError('Medio aprobado ausente o cambiado: '+str(relative))
    target=public/relative
    if not target.is_file() or hashlib.sha256(target.read_bytes()).hexdigest()!=item['sha256']:
        raise RuntimeError('El paquete alteró un medio aprobado: '+str(relative))
subprocess.run([sys.executable,str(root/'scripts/aplicar-rutas-lugares.py'),'--release',str(release)],check=True)
subprocess.run([sys.executable,str(root/'scripts/agregar-metadatos-sociales.py'),'--public',str(public)],check=True)
# Retired controller stays inventoried for a separate, explicit cleanup; no template loads it.
# Explicit retirement is verified after composition; no legacy controller is restored.
subprocess.run([sys.executable,str(root/'scripts/consolidar-shell-css.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/componer-presentacion.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/generar-paginas-contextuales.py'),'--public',str(public)],check=True)
subprocess.run(['php',str(root/'scripts/normalizar-destinos.php'),str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/versiones-presentacion.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/retirar-recursos-presentacion.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/versionar-recursos.py'),'--public',str(public)],check=True)
print(f'Entrega de revisión: {release}\nRaíz pública: {public}\nConfigurar private/config.php fuera de public/. No hay publicación automática.')

# Fail the package if retired identity assets or palette reappear.
subprocess.run([sys.executable,str(root/'preprod/probar-identidad-vigente.py'),str(public)],check=True)
