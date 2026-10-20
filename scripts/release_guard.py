"""Release invariants: no hidden runtime delta and no unverified main promotion."""
import hashlib
import json
import subprocess


def require_complete_delta(changed, selected):
    omitted = sorted({f['relative'] for f in changed} - {f['relative'] for f in selected})
    if omitted:
        raise ValueError('El candidato contiene cambios desplegables fuera del alcance: '
                         + ', '.join(omitted)
                         + '. Separarlos del candidato o revisar/aprobar el alcance completo; no publicar parcialmente y declarar main sincronizado.')


def require_no_missing_files(files, manifest):
    missing = sorted({f['relative'] for f in manifest['files']} - {f['relative'] for f in files})
    if missing:
        raise ValueError('El paquete omite archivos publicados: '+', '.join(missing)+'. Reconciliar fuentes; los retiros requieren una tarea separada.')


def inventory(files):
    result = {}
    for f in files:
        key = f['relative']
        if key in result:
            raise ValueError('Ruta duplicada en inventario: ' + key)
        result[key] = f['sha256']
    return result


def require_package_parity(files, manifest):
    expected, actual = inventory(manifest['files']), inventory(files)
    differences = sorted(k for k in expected.keys() | actual.keys() if expected.get(k) != actual.get(k))
    if differences:
        raise ValueError('La reconstrucción no coincide con producción: ' + ', '.join(differences))


def manifest_digest(manifest):
    return hashlib.sha256(json.dumps(manifest, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def require_validation(manifest, report):
    checks = report.get('checks', {})
    required = {'public_pages', 'accounts', 'statistics', 'assets', 'database'}
    if (report.get('schema_version') != 1 or report.get('commit') != manifest.get('commit')
            or not manifest.get('commit') or report.get('manifest_sha256') != manifest_digest(manifest)
            or not isinstance(checks, dict) or not required.issubset(checks)
            or not all(checks[k] is True for k in required)):
        raise ValueError('Falta validación funcional de producción vinculada al manifiesto exacto; no actualizar main.')


def require_main_ancestor(root):
    for ref in ('refs/heads/main', 'refs/remotes/origin/main'):
        exists = subprocess.run(['git', 'rev-parse', '--verify', ref], cwd=root, capture_output=True)
        if exists.returncode:
            raise ValueError('Falta la referencia ' + ref + '; actualizar referencias antes de publicar.')
        ancestor = subprocess.run(['git', 'merge-base', '--is-ancestor', ref, 'HEAD'], cwd=root, capture_output=True)
        if ancestor.returncode:
            raise ValueError(ref + ' no está integrado en preprod. Resolver y probar antes de publicar; no usar force ni ours/theirs global.')
