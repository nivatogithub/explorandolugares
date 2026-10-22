#!/usr/bin/env python3
"""Retira páginas reemplazadas por PHP y créditos públicos del paquete de hosting.

No modifica fuentes ni elimina imágenes del repositorio. Solo limpia la copia
generada para producción, manteniendo todo archivo referenciado por páginas,
assets o el catálogo dinámico.
"""
from pathlib import Path
import argparse
import json
import re


def remove_redundant_pages(public: Path, catalog: Path) -> list[str]:
    data=json.loads(catalog.read_text())
    slugs=[item['slug'] for item in data.get('destinations',[]) if isinstance(item,dict) and item.get('slug')]
    removed=[]
    for locale in ('', 'en/', 'pt-br/'):
        for slug in slugs:
            page=public/f'{locale}destinos/{slug}/index.html'
            if page.is_file():
                page.unlink();removed.append(page.relative_to(public).as_posix())
    return removed


def remove_credit_pages(public: Path) -> list[str]:
    removed=[]
    for locale in ('', 'en/', 'pt-br/'):
        folder=public/f'{locale}creditos-fotograficos'
        for page in folder.rglob('*') if folder.exists() else []:
            if page.is_file():
                page.unlink();removed.append(page.relative_to(public).as_posix())
        if folder.exists():
            for child in sorted(folder.rglob('*'),reverse=True):
                if child.is_dir(): child.rmdir()
            folder.rmdir()
    return removed


def remove_credit_links(public: Path) -> int:
    count=0
    patterns=(
        r'\s*·\s*<a\s+href="[^"]*creditos-fotograficos/index\.html"[^>]*>.*?</a>',
        r'<a\s+href="[^"]*creditos-fotograficos/index\.html"[^>]*>.*?</a>\s*·\s*',
    )
    for page in list(public.rglob('*.html'))+list(public.rglob('*.php')):
        source=page.read_text(errors='strict')
        for pattern in patterns:
            source,n=re.subn(pattern,'',source,flags=re.S|re.I)
            count+=n
        page.write_text(source)
    return count


def remove_credit_sitemap_entries(public: Path) -> int:
    sitemap=public/'sitemap.xml'
    if not sitemap.is_file(): return 0
    source=sitemap.read_text()
    source,count=re.subn(r'\s*<url>\s*<loc>[^<]*creditos-fotograficos/</loc>\s*</url>', '', source, flags=re.I)
    sitemap.write_text(source)
    return count


def replace_legacy_jerico_card(public: Path) -> int:
    """Use Jericó's selected illustration in public cards instead of the old photo."""
    count=0
    pattern=re.compile(r'(?P<prefix>(?:\.\./)*)assets/jerico\.webp')
    replacements={
        'Parque principal de Jericó al atardecer':'Ilustración editorial del parque principal de Jericó',
        "Jericó&#x27;s main square at dusk":"Editorial illustration of Jericó&#x27;s main square",
        'Parque principal de Jericó ao pôr do sol':'Ilustração editorial do parque principal de Jericó',
    }
    for page in public.rglob('*'):
        if not page.is_file() or page.suffix.lower() not in {'.html','.php','.css','.js','.json','.xml','.txt'}: continue
        source=page.read_text(errors='ignore')
        source,n=pattern.subn(lambda match:match.group('prefix')+'assets/destinos/1/parque-principal-ilustrado.webp',source)
        count+=n
        for old,new in replacements.items(): source=source.replace(old,new)
        page.write_text(source)
    return count


def prune_unreferenced_destination_images(public: Path, catalog: Path) -> list[str]:
    """Remove unused Jericó image candidates, keeping DB seed references intact."""
    references=[]
    for page in list(public.rglob('*')):
        if page.is_file() and page.suffix.lower() in {'.html','.php','.css','.js','.json','.xml','.txt'}:
            references.append(page.read_text(errors='ignore'))
    # The private seed also records reference_asset_path values for provenance.
    # Those source files are not public runtime assets and must not keep an
    # obsolete photograph in the public release. Only published media paths
    # belong in the package's asset reference set.
    seed=json.loads(catalog.read_text())
    media_paths=[str(item.get('path','')) for item in seed.get('media',[]) if item.get('path')]
    text='\n'.join(references)
    candidates=[public/'assets/jerico.webp']
    folders=[public/'assets/jerico',public/'assets/destinos/1']
    for folder in folders:
        if folder.exists(): candidates.extend(p for p in folder.rglob('*') if p.is_file())
    removed=[]
    for asset in candidates:
        relative=asset.relative_to(public).as_posix()
        if asset.is_file() and relative not in text and relative not in media_paths:
            asset.unlink();removed.append(relative)
    for folder in folders:
        if folder.exists():
            for child in sorted(folder.rglob('*'),reverse=True):
                if child.is_dir(): child.rmdir()
            if not any(folder.iterdir()): folder.rmdir()
    return removed


def prune_unreferenced_assets(public: Path, catalog: Path, app: Path|None=None) -> list[str]:
    """Keep package assets referenced by runtime or the private destination seed."""
    text_extensions={'.html','.php','.css','.js','.json','.xml','.txt','.md','.sql'}
    references=[]
    for root in (public, app):
        if root is None or not root.is_dir(): continue
        for source in root.rglob('*'):
            if source.is_file() and source.suffix.lower() in text_extensions:
                references.append(source.read_text(errors='ignore').lower())
    # The private seed contains the paths read dynamically after import, even
    # when a destination or attraction is still a draft and has no public HTML.
    references.append(catalog.read_text(errors='ignore').lower())
    text='\n'.join(references)
    removed=[]
    license_names=('license','licence','copying','notice')
    for asset in (public/'assets').rglob('*'):
        # Runtime coverage requests construct cartography URLs dynamically.
        # Previously the builder restored this entire reviewed directory after
        # pruning; preserve its single selected producer instead of copying twice.
        if asset.is_relative_to(public/'assets/cobertura'):
            continue
        if (asset.is_file() and not any(token in asset.name.lower() for token in license_names)
                and asset.name.lower() not in text):
            removed.append(asset.relative_to(public).as_posix())
            asset.unlink()
    for folder in sorted((public/'assets').rglob('*'),reverse=True):
        if folder.is_dir() and not any(folder.iterdir()):
            folder.rmdir()
    return removed


def clean(public: Path, catalog: Path, app: Path|None=None) -> dict:
    if not public.is_dir() or not catalog.is_file():
        raise ValueError('Se requieren la raíz pública y el catálogo dinámico del paquete.')
    removed_pages=remove_redundant_pages(public,catalog)
    removed_credits=remove_credit_pages(public)
    links=remove_credit_links(public)
    sitemap_entries=remove_credit_sitemap_entries(public)
    card_images=replace_legacy_jerico_card(public)
    removed_photos=prune_unreferenced_destination_images(public,catalog)
    removed_unreferenced=prune_unreferenced_assets(public,catalog,app)
    return {'removed_destination_pages':removed_pages,'removed_credit_pages':removed_credits,
            'removed_credit_links':links,'removed_credit_sitemap_entries':sitemap_entries,
            'updated_jerico_card_images':card_images,
            'removed_legacy_jerico_photos':removed_photos,
            'removed_unreferenced_assets':removed_unreferenced}


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--public',type=Path,required=True)
    parser.add_argument('--catalog',type=Path,required=True)
    parser.add_argument('--app',type=Path)
    args=parser.parse_args()
    result=clean(args.public.resolve(),args.catalog.resolve(),args.app.resolve() if args.app else None)
    print(json.dumps(result,ensure_ascii=False,indent=2))
