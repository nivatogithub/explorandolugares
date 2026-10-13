#!/usr/bin/env python3
"""Genera robots.txt, sitemap.xml y las directivas de indexación públicas."""
from pathlib import Path, PurePosixPath
from xml.sax.saxutils import escape

ROOT=Path(__file__).resolve().parents[1]
PUBLIC=ROOT/'ensayos/v1'
SITE='https://explorandolugares.com'

# Páginas de navegación o contenido aprobado para aparecer en buscadores.
PRIVATE_PREFIXES=(('admin',),('api',))
NOINDEX_PATHS={
    ('agenda','index.html'), ('en','agenda','index.html'),
    ('admin','vista-previa.html'), ('en','admin','vista-previa.html'),
    ('jerico.html',), ('privacidad.html',),
    ('en','jerico.html'), ('en','privacidad.html'),
}
NOINDEX_JERICO_SECTIONS={
    'agenda','alojamiento','bares-y-vida-nocturna','comercios-y-productores',
    'cultura-y-patrimonio','experiencias-y-naturaleza','gastronomia',
    'informacion-practica','servicios','transporte-y-movilidad',
}

def approved(relative: PurePosixPath) -> bool:
    parts=relative.parts
    if parts in NOINDEX_PATHS or any(parts[:len(prefix)]==prefix for prefix in PRIVATE_PREFIXES):
        return False
    for language in ((),('en',)):
        offset=len(language)
        if parts[:offset]==language and parts[offset:offset+2]==('destinos','jerico'):
            return len(parts)<offset+4 or parts[offset+2] not in NOINDEX_JERICO_SECTIONS
    return relative.name=='index.html'

def url_for(relative: PurePosixPath) -> str:
    parts=list(relative.parts[:-1])
    return SITE+'/'+'/'.join(parts)+('/' if parts else '')

def set_directive(path: Path, enabled: bool) -> None:
    content=path.read_text()
    directive='index,follow' if enabled else 'noindex,nofollow'
    marker='<meta name="robots" content="'
    start=content.find(marker)
    if start<0:
        raise ValueError(f'Falta directiva robots en {path.relative_to(PUBLIC)}')
    end=content.find('">',start)
    content=content[:start]+marker+directive+content[end:]
    path.write_text(content)

pages=[]
for path in sorted(PUBLIC.rglob('*.html')):
    relative=PurePosixPath(path.relative_to(PUBLIC).as_posix())
    enabled=approved(relative)
    set_directive(path,enabled)
    if enabled:
        pages.append(url_for(relative))

sitemap=['<?xml version="1.0" encoding="UTF-8"?>','<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
for url in pages:
    sitemap.append(f'  <url><loc>{escape(url)}</loc></url>')
sitemap.append('</urlset>')
(PUBLIC/'sitemap.xml').write_text('\n'.join(sitemap)+'\n')
(PUBLIC/'robots.txt').write_text('''User-agent: *
Allow: /
Disallow: /admin/
Disallow: /api/
Disallow: /en/admin/

Sitemap: https://explorandolugares.com/sitemap.xml
''')
print(f'OK: {len(pages)} páginas públicas indexables; sitemap.xml y robots.txt actualizados.')
