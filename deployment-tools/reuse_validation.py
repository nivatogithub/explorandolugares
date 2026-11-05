"""Reuse shared application checks only for an additive reviewed-content release."""
from pathlib import Path
import hashlib
import json
import subprocess


def original_data_preserved(before, after):
    if isinstance(before, dict):
        return isinstance(after, dict) and all(k in after and original_data_preserved(v, after[k]) for k, v in before.items())
    if isinstance(before, list):
        return isinstance(after, list) and len(after) >= len(before) and after[:len(before)] == before
    return before == after


def unexpected_changes(before, after, allowed, prefixes):
    return sorted(p for p in before.keys() | after.keys()
                  if before.get(p) != after.get(p) and p not in allowed
                  and not any(p.startswith(prefix) for prefix in prefixes))


def require_reusable_application_validation(root, previous, report, slug, folder, pack):
    import sys
    sys.path.insert(0, str(root / 'scripts'))
    from release_guard import require_validation
    require_validation(previous, report)
    baseline = report['deployed_commit_full']
    def git(*args):
        return subprocess.check_output(['git', *args], cwd=root, text=True).strip()
    if git('status', '--porcelain'):
        raise ValueError('El candidato debe estar limpio antes de reutilizar pruebas.')
    def tree(ref):
        return dict(line.split(' ', 1) for line in git('ls-tree', '-r', '--format=%(path) %(objectname)', ref).splitlines())
    metadata = {'backend/app/content/release-media-manifest.json', 'content/release-places-businesses.json', 'content/lugares-publicados.json'}
    payload = 'backend/app/content/approved-' + slug + '-20261105.json'
    allowed = metadata | {payload} | {'backend/public/' + m['asset_path'] for m in pack['tables']['media_assets']}
    differences = unexpected_changes(tree(baseline), tree('HEAD'), allowed, [folder.rstrip('/') + '/', 'deployment-tools/'])
    if differences:
        raise ValueError('Cambió código o contenido ajeno al lugar; requiere nueva validación: ' + ', '.join(differences[:8]))
    for path in metadata:
        old = json.loads(git('show', baseline + ':' + path))
        new = json.loads((root / path).read_text())
        if not original_data_preserved(old, new):
            raise ValueError('El candidato modifica datos anteriores: ' + path)
    return {'shared_application_checks_reused': True, 'compared_with_validated_commit': baseline,
            'original_validated_code_commit': report.get('application_validation', {}).get('original_validated_code_commit', baseline),
            'no_application_code_changed': True, 'previous_source_data_preserved': True,
            'new_content_requires_own_validation': True,
            'scope': slug, 'payload_sha256': hashlib.sha256((root / payload).read_bytes()).hexdigest()}
