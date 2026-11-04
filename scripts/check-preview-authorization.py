#!/usr/bin/env python3
"""Exercise the actual preview controller with real sessions and disposable SQLite copies."""
from pathlib import Path
import argparse
import json
import os
import sqlite3
import subprocess
import tempfile
from contextlib import closing

ROOT = Path(__file__).resolve().parents[1]
MARKER = 'PRIVATE-PREVIEW-SYNTHETIC-CONTENT'


def run(package, database, output):
    package, database = package.resolve(), database.resolve()
    assert all(path.is_relative_to(ROOT / '.local') for path in (package, database))
    assert database.is_file() and (package / 'public/place.php').is_file()
    checks = []
    cases = [('valid', True, 200), ('guest', True, 403), ('expired', True, 403),
             ('disabled', True, 403), ('role_revoked', True, 403), ('password_reset', True, 403),
             ('identity_mismatch', True, 403), ('flag_only', True, 403), ('valid', False, 404)]
    with tempfile.TemporaryDirectory(prefix='preview-auth-', dir=ROOT / '.local') as temporary:
        work = Path(temporary)
        for index, (case, preview, expected) in enumerate(cases):
            isolated = work / f'{index}.sqlite'
            with closing(sqlite3.connect(database)) as source, closing(sqlite3.connect(isolated)) as target:
                source.backup(target)
                row = target.execute('SELECT account_id FROM account_roles WHERE role="administrator" LIMIT 1').fetchone()
                assert row, 'Synthetic administrator fixture required'
                admin_id = row[0]
                target.execute('UPDATE destinations SET publication_status="draft" WHERE slug="jerico"')
                target.execute('UPDATE destination_translations SET intro=? WHERE destination_id=(SELECT id FROM destinations WHERE slug="jerico")', (MARKER,))
                target.commit()
            session_path = work / f'sessions-{index}'
            session_path.mkdir()
            code = 'require ' + json.dumps(str(package / 'app/AccountSessionSecurity.php')) + ';'
            code += '$pdo=new PDO(' + json.dumps('sqlite:' + str(isolated)) + ',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'
            code += 'session_save_path(' + json.dumps(str(session_path)) + ');session_name("el_session");session_start();'
            code += '$_SESSION=["admin"=>true,"admin_account_id"=>' + str(admin_id) + ',"business_account_id"=>' + str(admin_id) + ',"last_seen"=>time(),"business_last_seen"=>time()];'
            code += 'AccountSessionSecurity::bind($pdo,$_SESSION,' + str(admin_id) + ');'
            if case == 'guest':
                code += '$_SESSION=[];'
            elif case == 'expired':
                code += '$_SESSION["last_seen"]=$_SESSION["business_last_seen"]=time()-3600;'
            elif case == 'disabled':
                code += '$pdo->exec("UPDATE accounts SET status=\'disabled\' WHERE id=' + str(admin_id) + '");'
            elif case == 'role_revoked':
                code += '$pdo->exec("DELETE FROM account_roles WHERE account_id=' + str(admin_id) + '");'
            elif case == 'password_reset':
                code += '$pdo->exec("UPDATE accounts SET password_hash=\'changed-credential\' WHERE id=' + str(admin_id) + '");'
            elif case == 'identity_mismatch':
                code += '$_SESSION["admin_account_id"]=' + str(admin_id + 999999) + ';'
            elif case == 'flag_only':
                code += '$_SESSION=["admin"=>true,"last_seen"=>time()];'
            code += 'session_write_close();unset($_SESSION);'
            code += '$_GET=["place"=>"jerico","locale"=>"es"' + (',"preview"=>"1"' if preview else '') + '];'
            code += '$_SERVER["REQUEST_URI"]="/lugares/jerico/";$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["HTTP_HOST"]="candidate.localhost";'
            code += 'ob_start();register_shutdown_function(function(){$body=ob_get_clean();echo json_encode(["status"=>http_response_code()?:200,"private_content"=>str_contains($body,' + json.dumps(MARKER) + ')]);});'
            code += 'require ' + json.dumps(str(package / 'public/place.php')) + ';'
            process = subprocess.run(['php', '-d', 'display_errors=stderr', '-r', code],
                                     env=os.environ | {'EL_DESTINATIONS_DSN': 'sqlite:' + str(isolated)},
                                     text=True, capture_output=True, check=True)
            assert not process.stderr.strip(), (case, process.stderr[:200])
            result = json.loads(process.stdout)
            assert result['status'] == expected, (case, expected, result)
            assert result['private_content'] == (preview and expected == 200), (case, result)
            checks.append({'case': case, 'preview': preview, 'http': expected, 'private_content_protected': True})
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps({'production_accessed': False, 'checks': checks}, indent=2) + '\n')
    print(f'OK: {len(checks)} real-controller/session scenarios protect draft content and reject revoked access.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--package', type=Path, required=True)
    parser.add_argument('--database', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    run(args.package, args.database, args.output)
