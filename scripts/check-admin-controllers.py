#!/usr/bin/env python3
"""Render private controllers against an isolated synthetic fixture, without an HTTP server."""
from pathlib import Path
from urllib.parse import urlsplit
from html.parser import HTMLParser
import argparse
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]


class AdminPage(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []
        self.resources = []

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'a' and values.get('href'):
            self.links.append(values['href'])
        if tag == 'script' and values.get('src'):
            self.resources.append(values['src'])
        if tag == 'link' and values.get('rel') == 'stylesheet':
            self.resources.append(values['href'])


def run(package, output):
    package = package.resolve()
    assert package.is_relative_to(ROOT / '.local')
    assert (package / 'private/presentation.sqlite').is_file()
    public = package / 'public'
    files = ['places.php', 'businesses.php', 'business-support.php', 'accounts.php',
             'account-applications.php', 'claims.php', 'subscriptions.php', 'coupons.php',
             'campaigns.php', 'email-templates.php', 'reports.php', 'public-requests.php',
             'database.php', 'incidents.php', 'settings.php', 'payment-settings.php',
             'costs.php', 'cancellations.php', 'transfer-businesses.php']
    checks = []
    for name in files:
        route = '/admin/' + name
        code = '$_SESSION=["admin"=>true,"last_seen"=>time(),"csrf"=>"isolated-test"];'
        code += '$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REQUEST_URI"]=' + json.dumps(route) + ';'
        code += '$_SERVER["HTTP_HOST"]="candidate.localhost";$_SERVER["REMOTE_ADDR"]="127.0.0.1";'
        code += 'require ' + json.dumps(str(public / 'admin' / name)) + ';'
        result = subprocess.run(['php', '-d', 'display_errors=stderr', '-r', code],
                                capture_output=True, text=True, check=True)
        assert not result.stderr.strip(), (route, result.stderr[:200])
        assert '<html lang="es"' in result.stdout, route
        assert 'Fatal error' not in result.stdout and 'Configuración del servidor pendiente' not in result.stdout, route
        page = AdminPage()
        page.feed(result.stdout)
        for link in page.links + page.resources:
            path = urlsplit(link).path
            if not path or urlsplit(link).netloc:
                continue
            if path.startswith('/'):
                target = public / path.lstrip('/')
            else:
                target = public / 'admin' / path
            if path.endswith(('.php', '.js', '.css')):
                assert target.is_file(), (route, link)
        checks.append({'route': route, 'spanish_interface': True, 'dependencies_present': True})
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps({'production_accessed': False, 'checks': checks}, indent=2) + '\n')
    print(f'OK: {len(checks)} private controllers render in Spanish with valid links and resources.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--package', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    run(args.package, args.output)
