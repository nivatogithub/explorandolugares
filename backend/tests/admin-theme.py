from pathlib import Path
root=Path(__file__).resolve().parents[1]
pages=[p for p in (root/'public/admin').glob('*.php') if '</head>' in p.read_text() and p.name!='negocio-vista-previa.php']
assert len(pages)>=18
for page in pages:
 text=page.read_text()
 assert "require __DIR__.'/_theme.php'" in text,page.name
 assert 'class="portal-admin' in text,page.name
 assert 'assets/logo.svg' not in text,page.name
css=(root/'public/assets/admin-theme.css').read_text()
import json
resources=json.loads((root/'app/presentation/resources.json').read_text())
assert 'identidad-neutral.css' in resources['admin']['css'] and 'admin.css' in resources['admin']['css']
assert "PortalPresentation::assets(false,'admin')" in (root/'public/admin/_theme.php').read_text()
assert 'var(--el-action)' in css and 'body.portal-admin' in css
assert 'floating-select-trigger' in css and '.health-critical' in css
print(f'OK: {len(pages)} plantillas administrativas comparten tema y logotipo vigente; estilos aislados del portal público.')
