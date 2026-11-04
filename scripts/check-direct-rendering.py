#!/usr/bin/env python3
"""Compare final destination DOMs against the pre-refactor package, using one isolated DB."""
from html import unescape
from html.parser import HTMLParser
from pathlib import Path
import argparse
import difflib
import json
import os
import subprocess

ROOT = Path(__file__).resolve().parents[1]
VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}


class Structure(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.tokens = []
        self.json_ld = False

    def handle_starttag(self, tag, attrs):
        if tag == 'script':
            self.json_ld = dict(attrs).get('type') == 'application/ld+json'
        cleaned = []
        for key, value in attrs:
            if key == 'class':
                value = ' '.join(sorted((value or '').split()))
            cleaned.append((key, value))
        self.tokens.append(('start', tag, sorted(cleaned)))

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID:
            self.handle_endtag(tag)

    def handle_endtag(self, tag):
        if tag == 'script':
            self.json_ld = False
        if tag not in VOID:
            self.tokens.append(('end', tag))

    def handle_data(self, text):
        if self.json_ld:
            # The retired DOM serializer encoded accented JSON string values as HTML
            # entities. Direct JSON-LD retains the actual Unicode names. Compare the
            # decoded data explicitly; no other script or page content is relaxed.
            def decode(value):
                if isinstance(value, str): return unescape(value)
                if isinstance(value, list): return [decode(v) for v in value]
                if isinstance(value, dict): return {k: decode(v) for k, v in value.items()}
                return value
            self.tokens.append(('json_ld', decode(json.loads(text))))
            return
        normalized = ' '.join(text.split())
        if normalized:
            self.tokens.append(('text', normalized))


def run(before, after, output):
    before, after = before.resolve(), after.resolve()
    assert all(p.is_relative_to(ROOT / '.local') for p in (before, after))
    database = before / 'private/presentation.sqlite'
    assert database.is_file() and not (after / 'app/DestinationPresentation.php').exists()
    controller = (after / 'public/place.php').read_text()
    assert not any(term in controller for term in ('DestinationPresentation', 'ob_start', 'DOMDocument', 'preg_replace'))
    release = json.loads((ROOT / 'content/release-places-businesses.json').read_text())
    checked = []
    for locale, prefix in [('es', ''), ('en', '/en'), ('pt-BR', '/pt-br')]:
        for slug in release['public_places']:
            route = prefix + '/lugares/' + slug + '/'
            trees = []
            for package in (before, after):
                query = json.dumps({'destino': slug, 'idioma': locale})
                code = '$_GET=json_decode(' + json.dumps(query) + ',true);$_SERVER["REQUEST_URI"]=' + json.dumps(route) + ';'
                code += '$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["HTTP_HOST"]="candidate.localhost";require ' + json.dumps(str(package / ('public/place.php' if (package / 'public/place.php').exists() else 'public/destino.php'))) + ';'
                result = subprocess.run(['php', '-d', 'display_errors=stderr', '-r', code],
                                        env=os.environ | {'EL_DESTINATIONS_DSN': 'sqlite:' + str(database)},
                                        text=True, capture_output=True, check=True)
                assert not result.stderr.strip(), (route, result.stderr)
                tree = Structure()
                tree.feed(result.stdout)
                trees.append(tree.tokens)
            if trees[0] != trees[1]:
                diff = list(difflib.unified_diff([str(t) for t in trees[0]], [str(t) for t in trees[1]], fromfile='before', tofile='direct'))
                raise AssertionError(route + '\n' + '\n'.join(diff[:100]))
            checked.append({'route': route, 'same_structure_content_actions_metadata': True})
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps({'database': 'shared disposable SQLite', 'production_accessed': False,
                                  'html_postprocessing_removed': True,
                                  'json_ld_unicode_encoding_corrected': True,
                                  'checks': checked}, ensure_ascii=False, indent=2) + '\n')
    print(f'OK: {len(checked)} pages preserve DOM structure, content, links, attributes and metadata with direct template rendering.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--before', type=Path, required=True)
    parser.add_argument('--after', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    run(args.before, args.after, args.output)
