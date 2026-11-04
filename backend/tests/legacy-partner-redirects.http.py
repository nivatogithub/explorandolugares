#!/usr/bin/env python3
"""Check legacy partner routes against an existing development environment."""
from urllib.error import HTTPError
from urllib.parse import parse_qs, urljoin, urlsplit
from urllib.request import HTTPRedirectHandler, build_opener
import sys


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return None


base = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8086'
client = build_opener(NoRedirect)
sections = {'': '', 'planes': 'suscripciones', 'interes': 'interes',
            'proceso': 'proceso', 'por-que-participar': 'por-que-participar',
            'impulsa-tu-destino': 'impulsa-tu-destino', 'impulsor': 'impulsor'}
for prefix in ('', 'en/', 'pt-br/'):
    for old, new in sections.items():
        for suffix in ('/', '/index.html?source=legacy%20link'):
            url = f'{base}/{prefix}aliados' + ('/' + old if old else '') + suffix
            try:
                client.open(url)
                raise AssertionError(f'{url} did not redirect')
            except HTTPError as response:
                assert response.code == 301, (url, response.code)
                target = urlsplit(urljoin(url, response.headers['Location']))
            expected = f'/{prefix}negocios-locales/' + (new + '/' if new else '')
            assert target.path == expected, (url, target.path, expected)
            if '?' in suffix:
                assert parse_qs(target.query)['source'] == ['legacy link'], target
            with build_opener().open(target.geturl()) as final:
                assert final.status == 200, (url, final.status)
print('OK: 42 legacy partner URLs preserve language, parameters and reach the corresponding page.')
