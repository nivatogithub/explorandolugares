"""Prepare versioned public data and synthetic accounts for the local MariaDB rehearsal.
Run through scripts/run-test-package.py; never reads production data.
"""
import pathlib,sys,subprocess,sqlite3,json,shutil
repo=pathlib.Path(__file__).resolve().parents[1];package=pathlib.Path(sys.argv[1]).resolve();
if not package.is_relative_to(repo/'.local') or not (package/'build-manifest.json').is_file():raise ValueError('Owned disposable package required')
out=repo/'.local/preprod/private/rehearsal87';out.mkdir(parents=True,exist_ok=True);out.chmod(0o700)
subprocess.run([sys.executable,'scripts/prepare-presentation-fixture.py','--package',str(package)],check=True)
db=sqlite3.connect(package/'private/presentation.sqlite');db.row_factory=sqlite3.Row
baseline={r[0]:[dict(row) for row in db.execute('SELECT * FROM "'+r[0]+'"')] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")}
release=repo/'content/releases/2026-11-05-87';scope=json.loads((release/'scope.json').read_text());manifest=json.loads((release/'manifest.json').read_text())
input={'baseline':baseline,'pack':json.loads((release/'public-content.json').read_text()),'scope':[s for batch in scope.values() for s in batch['slugs']],'media':manifest['media'],'heritage_sql':(repo/'content/place-labels/heritage-colombia-20261104.sql').read_text()}
(out/'input.json').write_text(json.dumps(input,ensure_ascii=False));(out/'input.json').chmod(0o600)
shutil.copyfile(repo/'preprod/rehearse-place-release.php',out/'runner.php');(out/'runner.php').chmod(0o600)
print('Prepared synthetic baseline: '+str(len(baseline))+' tables, 20 places; no production data.')
