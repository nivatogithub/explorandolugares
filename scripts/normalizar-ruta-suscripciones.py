#!/usr/bin/env python3
"""Publish the subscription URL consistently; retain only redirects at old paths."""
import argparse
from pathlib import Path

OLD = 'negocios-locales/planes/'
NEW = 'negocios-locales/suscripciones/'
RULE = r'RewriteRule ^((?:en/|pt-br/)?)negocios-locales/planes(?:/index\.html|/)?$ /$1negocios-locales/suscripciones/ [R=301,L,NE]'


def normalize(public):
    for prefix in ('', 'en/', 'pt-br/'):
        old = public / prefix / OLD / 'index.html'
        new = public / prefix / NEW / 'index.html'
        if old.exists() and 'data-subscription-redirect' not in old.read_text():
            new.parent.mkdir(parents=True, exist_ok=True)
            new.write_text(old.read_text())
        if not new.is_file():
            raise ValueError('Falta la página de suscripciones: ' + prefix)
    # Includes canonical/alternate tags, menus, PHP return links and cached assets.
    for page in public.rglob('*'):
        if page.is_file() and page.suffix in {'.html', '.php', '.js', '.json', '.xml'}:
            source = page.read_text()
            updated = source.replace(OLD, NEW)
            for quote in ('"', "'"):
                for prefix in ('', '../', './'):
                    updated = updated.replace('href=' + quote + prefix + 'planes/', 'href=' + quote + prefix + 'suscripciones/')
            if updated != source:
                page.write_text(updated)
    for prefix in ('', 'en/', 'pt-br/'):
        target = '/' + prefix + NEW
        old = public / prefix / OLD / 'index.html'
        old.write_text('<!doctype html><html data-subscription-redirect><head>'
                       '<meta charset="utf-8"><meta name="robots" content="noindex,follow">'
                       '<meta http-equiv="refresh" content="0;url=' + target + '">'
                       '<link rel="canonical" href="https://explorandolugares.com' + target + '">'
                       '<title>Suscripciones | Explorando Lugares</title></head><body>'
                       '<a href="' + target + '">Suscripciones</a></body></html>')
    access = public / '.htaccess'
    source = access.read_text()
    if RULE not in source:
        if 'RewriteEngine On' not in source:
            raise ValueError('Falta el enrutamiento Apache.')
        access.write_text(source.replace('RewriteEngine On', 'RewriteEngine On\n' + RULE, 1))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--public', type=Path, required=True)
    normalize(parser.parse_args().public)
