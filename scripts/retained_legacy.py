"""Temporary, hash-locked compatibility; no active page may load these resources."""
import hashlib
import json
from pathlib import Path


def capture(root, public):
    root, public = Path(root), Path(public)
    manifest = json.loads((root/'backend/app/presentation/retained-legacy.json').read_text())
    result = {}
    for item in manifest['files']:
        relative = Path(item['relative'])
        if relative.is_absolute() or '..' in relative.parts or relative.parts[0] != 'public':
            raise ValueError('Invalid compatibility path')
        source = root/'backend/retained-legacy'/relative
        if not source.is_file():
            source = public/Path(*relative.parts[1:])
        data = source.read_bytes()
        if hashlib.sha256(data).hexdigest() != item['sha256']:
            raise ValueError('Published compatibility bytes changed: '+str(relative))
        result[relative.as_posix()] = data
    return result


def restore(public, resources):
    public = Path(public)
    # Runtime declarations, rather than historical provenance, must be absent.
    for page in public.rglob('*'):
        if not page.is_file() or page.suffix not in {'.html', '.php', '.css', '.js'}:
            continue
        text = page.read_text()
        for relative in resources:
            needle = relative.removeprefix('public/')
            if needle in text:
                raise ValueError('Legacy resource still referenced: '+relative+' in '+str(page))
    for relative, data in resources.items():
        target = public/relative.removeprefix('public/')
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
