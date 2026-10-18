#!/usr/bin/env python3
"""Compare real HTTP responses. Never promote a read-only smoke test to E2E approval."""
from concurrent.futures import ThreadPoolExecutor
from collections import Counter
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlsplit
import argparse
import hashlib
import html
import json
import subprocess
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
ORIGINS = {'preprod': 'http://127.0.0.1:8088', 'produccion': 'https://explorandolugares.com'}
PLACES = ['jerico', 'cartagena', 'bogota', 'medellin', 'santa-marta']
ROUTES = ['/', '/destinos/', '/planea-tu-visita/', '/negocios-locales/',
          '/negocios-locales/planes/', '/negocios-locales/proceso/', '/cobertura/colombia/',
          '/contacto.php', '/privacidad/', '/negocios/acceso/', '/negocios/registro/',
          '/negocios/recuperar-clave/']
SCENARIOS = [
    ('E2E-01', 'Crear administrador técnico y acceder a cada opción del panel'),
    ('E2E-02', 'Registrar viajero desde cero, capturar correo simulado y activar por su enlace'),
    ('E2E-03', 'Acceder, navegar mostrando nombre y cerrar sesión'),
    ('E2E-04', 'Recuperar contraseña mediante el enlace generado y rechazar su reutilización'),
    ('E2E-05', 'Registrar persona responsable y crear negocio gratuito desde el portal público'),
    ('E2E-06', 'Buscar y reclamar negocio técnico sin propietario; revisar su vinculación'),
    ('E2E-07', 'Reclamar negocio técnico ocupado sin sustituir su responsable'),
    ('E2E-08', 'Contratar Destacado, Página y Premium con las cuatro periodicidades'),
    ('E2E-09', 'Presentar comprobante simulado y validarlo como administrador técnico'),
    ('E2E-10', 'Comprobar complemento de experiencia y rechazo a negocio no operador'),
    ('E2E-11', 'Verificar tarjetas y páginas de los cuatro niveles en escritorio y móvil'),
    ('E2E-12', 'Retirar únicamente datos, archivos, sesiones y simulaciones de la ejecución'),
]


class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.resources, self.links, self.businesses, self.headings = set(), set(), Counter(), []
        self.business = None
        self.title, self.in_title, self.h1, self.in_h1 = '', False, '', False

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'title': self.in_title = True
        if tag == 'h1': self.in_h1, self.h1 = True, ''
        if tag in ('img', 'script') and a.get('src'): self.resources.add(a['src'])
        if tag == 'link' and a.get('rel') == 'stylesheet' and a.get('href'): self.resources.add(a['href'])
        if tag == 'a' and a.get('href'): self.links.add(a['href'])
        if tag == 'article' and a.get('id', '').startswith('negocio-'):
            # Persistent local subscription fixtures are deliberately absent in production.
            if not a['id'].startswith('negocio-f00d0000-'):
                self.business = (a.get('data-categories', a.get('data-category', '')), a.get('class', ''))
        if tag == 'h3' and 'directory-business-title' in a.get('class', '') and self.business is not None:
            # Import reconciliation legitimately preserves an existing production UUID.
            # Compare full names/categories/tier with multiplicity, never only row counts.
            self.businesses[(a.get('title', ''), *self.business)] += 1

    def handle_endtag(self, tag):
        if tag == 'article': self.business = None
        if tag == 'title': self.in_title = False
        if tag == 'h1': self.in_h1 = False; self.headings.append(' '.join(self.h1.split()))

    def handle_data(self, data):
        if self.in_title: self.title += data
        if self.in_h1: self.h1 += data


def fetch(origin, route):
    url = urljoin(origin + '/', route)
    # Do not follow an internal link to another host or a downgrade to HTTP.
    class PinnedRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            if urlsplit(newurl).netloc != urlsplit(origin).netloc or urlsplit(newurl).scheme != urlsplit(origin).scheme:
                raise ValueError('Redirección fuera del ambiente')
            return super().redirect_request(req, fp, code, msg, headers, newurl)
    try:
        with urllib.request.build_opener(PinnedRedirect()).open(url, timeout=30) as r:
            return r.status, r.read(), r.geturl(), r.headers.get_content_type()
    except urllib.error.HTTPError as e:
        return e.code, e.read(), e.url, e.headers.get_content_type()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / '.local/production/regresion-paridad')
    args = parser.parse_args()
    output = args.output.resolve(); output.mkdir(parents=True, exist_ok=True)
    results, resources, links = [], set(), set()

    def case(key, title, fn):
        try:
            evidence = fn()
            row = dict(id=key, title=title, status='APROBADA', evidence=evidence)
        except (urllib.error.URLError, TimeoutError) as e:
            row = dict(id=key, title=title, status='BLOQUEADA', detail='No se pudo completar la consulta HTTP: ' + str(e)[:300])
        except Exception as e:
            row = dict(id=key, title=title, status='FALLIDA', detail=str(e)[:500])
        results.append(row)
        print(key, row['status'], title, flush=True)

    def compare(route):
        pages = {}
        for name, origin in ORIGINS.items():
            status, body, final, mime = fetch(origin, route)
            assert status == 200, f'{name}: HTTP {status} en {route}'
            assert mime == 'text/html', f'{name}: respuesta no HTML en {route}'
            p = Page(); p.feed(body.decode('utf-8', errors='replace')); pages[name] = p
            assert p.title.strip(), f'{name}: falta título'
            for asset in p.resources:
                u = urlsplit(urljoin(origin + route, asset))
                if u.netloc == urlsplit(origin).netloc:
                    resources.add((name, u.path + ('?' + u.query if u.query else '')))
            for link in p.links:
                u = urlsplit(urljoin(origin + route, link))
                if u.netloc == urlsplit(origin).netloc and u.scheme in ('http', 'https'):
                    # Logout and other action links must never be crawled as navigation.
                    if any(x in u.path.lower() for x in ('logout', 'salir', 'cerrar', 'eliminar', 'borrar')): continue
                    if u.path.startswith(('/admin/', '/api/', '/negocios/', '/cuenta/', '/suscripciones/')): continue
                    if u.query: continue
                    links.add((name, u.path))
        a, b = pages.values()
        assert a.title.strip() == b.title.strip(), f'Títulos diferentes: {route}'
        assert a.headings == b.headings, f'Encabezados principales diferentes: {route}'
        assert a.resources == b.resources, f'Referencias de diseño/imágenes diferentes: {route}'
        assert a.businesses == b.businesses, f'Directorio diferente: {route} ({len(a.businesses)}/{len(b.businesses)})'
        return {'route': route, 'title': b.title.strip(), 'businesses': len(b.businesses)}

    routes = ROUTES + ['/' + lang + 'destinos/' + slug + '/' for slug in PLACES for lang in ('', 'en/', 'pt-br/')]
    with ThreadPoolExecutor(max_workers=4) as pool:
        comparisons = [(route, pool.submit(compare, route)) for route in routes]
        for i, (route, future) in enumerate(comparisons, 1):
            case(f'PAR-{i:02}', route, future.result)

    def check_asset(item):
        name, path = item
        status, body, _, mime = fetch(ORIGINS[name], path)
        assert status == 200 and body, f'{name}: recurso {path}, HTTP {status}'
        assert mime != 'text/html', f'{name}: recurso devuelve HTML: {path}'
        return name, path, hashlib.sha256(body).hexdigest()

    def assets():
        assert resources, 'No se descubrieron recursos; no se puede aprobar esta comprobación'
        with ThreadPoolExecutor(max_workers=4) as pool: checked = list(pool.map(check_asset, sorted(resources)))
        hashes = {(name, path): digest for name, path, digest in checked}
        for name, path in hashes:
            other = 'produccion' if name == 'preprod' else 'preprod'
            if (other, path) in hashes:
                assert hashes[name, path] == hashes[other, path], f'Contenido del recurso diferente: {path}'
        return {'resources': len(checked), 'comparison': 'SHA-256 de contenido servido'}
    case('ASSET', 'Imágenes, CSS y JavaScript cargan y coinciden', assets)

    def check_link(item):
        name, path = item
        status, _, _, _ = fetch(ORIGINS[name], path)
        assert status == 200, f'{name}: enlace {path}, HTTP {status}'
        return {'environment': name, 'path': path}

    def navigation():
        assert links, 'No se descubrieron enlaces; no se puede aprobar esta comprobación'
        with ThreadPoolExecutor(max_workers=4) as pool: checked = list(pool.map(check_link, sorted(links)))
        return {'links': len(checked)}
    case('LINK', 'Enlaces públicos descubiertos en las páginas', navigation)

    def admin_guard():
        for path in ('/admin/index.php', '/admin/base-datos.php', '/admin/medios-pago.php'):
            status, _, final, _ = fetch(ORIGINS['produccion'], path)
            assert status in (401, 403) or (status == 200 and '/negocios/acceso/' in final), f'Administración abierta: {path}'
        return {'local_admin_exception': 'El acceso local sin clave no se exige ni se permite como paridad de producción.'}
    case('AUTH-GUARD', 'Producción rechaza acceso administrativo anónimo', admin_guard)
    for key, title in SCENARIOS:
        results.append(dict(id=key, title=title, status='BLOQUEADA', detail='Falta ejecutor de fixtures de producción con aislamiento de correos, pagos y limpieza. Esta comprobación HTTP de lectura no ejecuta ni aprueba el flujo.'))
    results.append(dict(id='VISUAL', title='Interacción y revisión visual en escritorio/móvil', status='BLOQUEADA', detail='HTML y hashes iguales no prueban interacción JavaScript ni composición visual. Requiere navegador del mismo candidato.'))
    report = {'at': datetime.now(timezone.utc).isoformat(), 'revision': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
              'working_tree_dirty': bool(subprocess.check_output(['git', 'status', '--porcelain'], cwd=ROOT, text=True)),
              'origins': ORIGINS, 'ready': False, 'results': results,
              'scope': 'HTTP real de ambos ambientes; solo lectura. No envía correos, no crea cuentas ni altera pagos. Una simulación no certifica entrega de correo ni transferencias bancarias.'}
    (output / 'resultado.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    rows = ''.join('<tr><td>' + html.escape(r['id']) + '</td><td>' + html.escape(r['title']) + '</td><td>' + r['status'] + '</td><td>' + html.escape(r.get('detail', json.dumps(r.get('evidence', {}), ensure_ascii=False))) + '</td></tr>' for r in results)
    (output / 'resultado.html').write_text('<!doctype html><html lang="es"><meta charset="utf-8"><title>Paridad preprod y producción</title><style>body{font:16px/1.5 system-ui;max-width:1200px;margin:32px auto;padding:16px}table{border-collapse:collapse}td,th{border:1px solid #ddd;padding:10px;text-align:left}h1{color:#943d30}</style><h1>Pase completo pendiente</h1><p>' + html.escape(report['scope']) + '</p><p>Revisión ' + report['revision'] + '</p><table><tr><th>ID</th><th>Prueba</th><th>Resultado</th><th>Evidencia o bloqueo</th></tr>' + rows + '</table></html>')
    print(output / 'resultado.html')
    return 1  # Complete E2E and visual acceptance are mandatory; never return a false green.


if __name__ == '__main__':
    raise SystemExit(main())
