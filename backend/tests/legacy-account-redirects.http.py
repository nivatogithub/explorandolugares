#!/usr/bin/env python3
"""Check old account URLs against an existing development environment."""
from urllib.error import HTTPError
from urllib.parse import parse_qs, urljoin, urlsplit
from urllib.request import HTTPRedirectHandler, build_opener
import sys


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return None


base = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8086'
client = build_opener(NoRedirect)
for locale in ('en', 'pt-br'):
    for page in ('acceso', 'registro'):
        for query in ('', '?next=%2Flugares%2Fjerico%2F&lang=es'):
            url = f'{base}/{locale}/negocios/{page}/{query}'
            try:
                client.open(url)
                raise AssertionError(f'{url} did not redirect')
            except HTTPError as response:
                assert response.code == 301, (url, response.code)
                target = urlsplit(urljoin(url, response.headers['Location']))
            assert target.path == f'/negocios/{page}/', target
            params = parse_qs(target.query)
            assert params['lang'][-1] == locale, target
            if query:
                assert params['next'] == ['/lugares/jerico/'], target
            with build_opener().open(target.geturl()) as final:
                html = final.read().decode('utf-8')
                assert final.status == 200, (url, final.status)
                assert f'<html lang="{locale}"' in html or (locale == 'pt-br' and '<html lang="pt-BR"' in html), url
                assert 'noindex' in html.lower(), url
print('OK: 8 legacy account redirects, parameters, language, final response and noindex.')
