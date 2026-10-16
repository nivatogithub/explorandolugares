#!/usr/bin/env python3
"""Content-version shared measurement loaders without altering page content."""
from pathlib import Path
import argparse,hashlib,re


def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()[:12]


def stamp(text,name,version):
    # Match an asset URL, not a selector or a bare filename in program logic.
    pattern=r'((?:[./]*assets/)(?:cobertura/)?'+re.escape(name)+r')(?:\?v=[A-Za-z0-9-]+)?'
    return re.sub(pattern,lambda m:m[1]+'?v='+version,text)


def versions(measurement,menu):
    measurement_version=digest(measurement)
    menu_text=stamp(menu.read_text(),'medicion.js',measurement_version)
    return measurement_version,menu_text,hashlib.sha256(menu_text.encode()).hexdigest()[:12]


def stamp_public(public):
    measurement_version,menu_text,menu_version=versions(public/'assets/cobertura/medicion.js',public/'assets/menu-principal.js')
    (public/'assets/menu-principal.js').write_text(menu_text)
    for path in public.rglob('*'):
        if path.is_file() and path.suffix in {'.html','.php'}:
            source=path.read_text();updated=stamp(stamp(source,'medicion.js',measurement_version),'menu-principal.js',menu_version)
            if source!=updated:path.write_text(updated)


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--public',type=Path,required=True);args=parser.parse_args();stamp_public(args.public)
