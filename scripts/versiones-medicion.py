#!/usr/bin/env python3
"""Content-version shared measurement loaders without altering page content."""
from pathlib import Path
import argparse,hashlib,re

ACCOUNT_COMPONENTS=['account-page.css','account-registration.css','account-access.css',
                    'account-header.css','account-entry.css','account-residence.js',
                    'business-holder.js','business-registration.js',
                    'business-subscriptions.js','select-flotante.js']

import sys
sys.path.insert(0,str(Path(__file__).resolve().parent))
from resource_markup import stamp_url

def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()[:12]


def stamp(text,name,version):
    # Match an asset URL, not a selector or a bare filename in program logic.
    pattern=r'((?:[./]*assets/)(?:cobertura/)?'+re.escape(name)+r')(?:\?v=[A-Za-z0-9-]+)?'
    return stamp_url(text,pattern,lambda m:m[1]+'?v='+version)


def versions(measurement,menu):
    css=measurement.with_suffix('.css')
    measurement_text=stamp(measurement.read_text(),'medicion.css',digest(css))
    measurement.write_text(measurement_text)
    measurement_version=digest(measurement)
    menu_text=stamp(menu.read_text(),'medicion.js',measurement_version)
    return measurement_version,menu_text,hashlib.sha256(menu_text.encode()).hexdigest()[:12]


def stamp_public(public):
    # Stamp account dependencies before hashing their loaders. Otherwise a new
    # page can keep running cached authentication/select code from an old release.
    component_versions={name:digest(public/'assets'/name) for name in ACCOUNT_COMPONENTS if (public/'assets'/name).is_file()}
    for name in ['account-entry.js','menu-principal.js']:
        path=public/'assets'/name
        if not path.is_file():continue
        source=path.read_text()
        for asset,version in component_versions.items():source=stamp(source,asset,version)
        path.write_text(source)
        if name=='account-entry.js':component_versions[name]=digest(path)
    measurement_version,menu_text,menu_version=versions(public/'assets/cobertura/medicion.js',public/'assets/menu-principal.js')
    (public/'assets/menu-principal.js').write_text(menu_text)
    for path in public.rglob('*'):
        if path.is_file() and path.suffix in {'.html','.php'}:
            source=path.read_text();updated=stamp(stamp(source,'medicion.js',measurement_version),'menu-principal.js',menu_version)
            for name,version in component_versions.items():updated=stamp(updated,name,version)
            if source!=updated:path.write_text(updated)


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--public',type=Path,required=True);args=parser.parse_args();stamp_public(args.public)
