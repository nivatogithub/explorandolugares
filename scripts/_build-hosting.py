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


def assert_dynamic_destination_boundary(public: Path, catalog: Path) -> None:
    """A destination has one renderer: public/place.php.

    Legacy HTML guides must never enter a release, even as an unused fallback.
    Keeping this check at the last point in the build protects against a later
    copy, overlay or restoration step reintroducing a second implementation.
    """
    if not (public/'place.php').is_file():
        raise RuntimeError('Falta el renderizador PHP único de destinos.')
    destination_renderer=(public/'place.php').read_text()
    if any(term in destination_renderer for term in ('DestinationPresentation', 'ob_start', 'DOMDocument', 'preg_replace')):
        raise RuntimeError('El destino debe renderizar su plantilla directamente, sin reparar el HTML generado.')
    data=json.loads(catalog.read_text())
    slugs=[str(item['slug']) for item in data.get('destinations', [])
           if isinstance(item, dict) and item.get('slug')]
    duplicates=[]
    for locale in ('', 'en/', 'pt-br/'):
        directory=public/f'{locale}destinos'
        if directory.exists():
            duplicates.extend(page.relative_to(public).as_posix() for page in directory.rglob('*.html'))
    if duplicates:
        raise RuntimeError('La entrega contiene páginas estáticas de destinos: '
                           + ', '.join(duplicates))
    if not (public/'places.php').is_file():
        raise RuntimeError('Falta el directorio PHP de destinos.')

parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output',type=Path,default=root/'.local/hosting')
args=parser.parse_args()
subprocess.run([sys.executable,str(root/'scripts/generate-contact-options.py')],check=True)
release=args.output.resolve()
public=release/'public'
public.mkdir(parents=True,exist_ok=True)
from public_inputs import collect
collect(root,public)
subprocess.run([sys.executable,str(root/'scripts/localize-coverage.py'),'--public',str(public)],check=True)
style=public/'assets/style.css'
if not style.is_file(): raise RuntimeError('Falta assets/style.css en la entrega pública.')
# Keep previous endpoint URLs without duplicating controller implementations.
aliases=json.loads((root/'backend/app/public-route-aliases.json').read_text())['aliases']
alias_rules=[]
for legacy,current in aliases.items():
    if not re.fullmatch(r'/[a-z0-9/-]+\.php',legacy) or not re.fullmatch(r'/[a-z0-9/-]+\.php',current):
        raise RuntimeError('Alias de controlador inválido.')
    if not (public/current.lstrip('/')).is_file() or (public/legacy.lstrip('/')).exists():
        raise RuntimeError('Alias sin destino único: '+legacy)
    alias_rules.append('RewriteRule ^'+re.escape(legacy.lstrip('/'))+'$ '+current.lstrip('/')+' [L,QSA]')
htaccess=public/'.htaccess'
htaccess.write_text(htaccess.read_text().replace('RewriteEngine On', 'RewriteEngine On\n'+'\n'.join(alias_rules),1))
shutil.copytree(root/'backend/app',release/'app',dirs_exist_ok=True)
retired_place_classes = ['DestinationAdminService', 'DestinationPackage', 'DestinationImporter',
                         'DestinationContentMigration', 'BusinessDestinationTransfer']
for old_class in retired_place_classes:
    if (release/'app'/f'{old_class}.php').exists():
        raise RuntimeError(f'Clase retirada del módulo de lugares: {old_class}.')
if (release/'app/DestinationRepository.php').exists():
    raise RuntimeError('DestinationRepository fue retirado; los consumidores deben usar PlaceRepository.')
if any((release/'app/presentation/destinations').glob('*.json')):
    raise RuntimeError('El contenido editorial no puede volver a publicarse como sobrescrituras por destino.')
shutil.copytree(root/'backend/bin',release/'bin',dirs_exist_ok=True)
content=release/'content';content.mkdir(exist_ok=True)
shutil.copyfile(root/'content/destinos-dinamicos.json',content/'destinos-dinamicos.json')
# El importador inicial queda privado junto al backend y a su catálogo semilla.
# La configuración real y los SQL no se copian a la entrega.
private_tools=release/'app/bin';private_tools.mkdir(parents=True,exist_ok=True)
shutil.copyfile(root/'backend/bin/import-places.php',private_tools/'import-places.php')
shutil.copyfile(root/'backend/bin/import-businesses.php',private_tools/'import-businesses.php')
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
    sys.executable,str(root/'scripts/clean-public-package.py'),
    '--public',str(public),'--catalog',str(private_content/'destinos-dinamicos.json'),
    '--app',str(release/'app')
],check=True)
for obsolete in ['logo.svg','logo-completo.svg','favicon.svg']:
    (public/'assets'/obsolete).unlink(missing_ok=True)
# Stamp all generated pages after the integration overlay, including general pages.
subprocess.run([sys.executable,str(root/'scripts/version-analytics.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/generate-release-sitemap.py'),'--public',str(public),'--catalog',str(private_content/'release-places-businesses.json')],check=True)
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
subprocess.run([sys.executable,str(root/'scripts/apply-place-routes.py'),'--release',str(release)],check=True)
subprocess.run([sys.executable,str(root/'scripts/check-social-metadata.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/check-shell-ownership.py'),'--public',str(public)],check=True)
# Retired controller stays inventoried for a separate, explicit cleanup; no template loads it.
# Explicit retirement is verified after composition; no legacy controller is restored.
subprocess.run([sys.executable,str(root/'scripts/compose-presentation.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/version-presentation.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/retire-presentation-resources.py'),'--public',str(public)],check=True)
subprocess.run([sys.executable,str(root/'scripts/version-resources.py'),'--public',str(public)],check=True)
print(f'Entrega de revisión: {release}\nRaíz pública: {public}\nConfigurar private/config.php fuera de public/. No hay publicación automática.')

# Fail the package if retired identity assets or palette reappear.
subprocess.run([sys.executable,str(root/'preprod/probar-identidad-vigente.py'),str(public)],check=True)

assert_dynamic_destination_boundary(public,private_content/'destinos-dinamicos.json')

# Cached pages may still request the previous resource filenames.
resource_aliases=json.loads((root/'backend/app/public-route-aliases.json').read_text()).get('resource_aliases',{})
resource_rules=[]
for legacy,current in resource_aliases.items():
    if not re.fullmatch(r'/assets/[a-z0-9/.-]+\.(?:js|css)',legacy) or '..' in legacy or '..' in current:
        raise RuntimeError('Alias de recurso inválido.')
    if (public/current.lstrip('/')).is_file():
        if (public/legacy.lstrip('/')).exists():raise RuntimeError('Recurso duplicado: '+legacy)
        resource_rules.append('RewriteRule ^'+re.escape(legacy.lstrip('/'))+'$ '+current.lstrip('/')+' [L,QSA]')
htaccess.write_text(htaccess.read_text().replace('RewriteEngine On','RewriteEngine On\n'+'\n'.join(resource_rules),1))
