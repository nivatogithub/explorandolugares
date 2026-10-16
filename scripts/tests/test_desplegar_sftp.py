import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

MODULE = Path(__file__).resolve().parents[1] / 'desplegar-sftp.py'
spec = importlib.util.spec_from_file_location('deploy', MODULE)
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


class DeploymentTests(unittest.TestCase):
    def setUp(self):
        self.config = {
            'site': 'explorandolugares', 'host': 'sftp.example.invalid', 'port': 22,
            'user': 'usuario', 'remote_base': '/home/usuario/explorandolugares',
            'public_directory': 'public', 'known_hosts_file': '~/.ssh/known_hosts', 'identity_file': '',
        }

    def load(self, config):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / 'config.json'
            path.write_text(json.dumps(config))
            return deploy.load_config(path)

    def test_valid_config_and_rejects_unsafe_targets_or_secrets(self):
        self.assertEqual(self.load(self.config)['site'], 'explorandolugares')
        invalid = [
            {'remote_base': '/'}, {'remote_base': '/home/user/public_html/otro'},
            {'remote_base': '/home/user/../otro'}, {'public_directory': 'private'},
            {'host': 'host;rm'}, {'password': 'no guardar'}, {'site': 'otro'}, {'port': 0},
        ]
        for changes in invalid:
            with self.subTest(changes=changes), self.assertRaises(ValueError):
                self.load({**self.config, **changes})

    def test_only_project_domain_root_is_allowed(self):
        config = {**self.config, 'public_root': '/home/usuario/public_html/explorandolugares.com'}
        self.assertEqual(deploy.public_path(self.load(config)), config['public_root'])
        for root in ['/home/usuario/public_html', '/home/usuario/public_html/otro.com',
                     '/home/otro/public_html/explorandolugares.com', '/home/usuario/public_html/../explorandolugares.com']:
            with self.subTest(root=root), self.assertRaises(ValueError):
                self.load({**self.config, 'public_root': root})

    def test_literal_paths_and_remote_boundary(self):
        self.assertEqual(deploy.quote('/tmp/mi carpeta'), '"/tmp/mi carpeta"')
        for value in ['a\nrm /', 'a*', 'a?', 'a[1]']:
            with self.assertRaises(ValueError):
                deploy.quote(value)
        for path in ['/tmp/x', 'app/../../outside', 'public/../secret']:
            with self.assertRaises(ValueError):
                deploy.remote_path(self.config, path)

    def test_production_package_rewrites_every_private_app_include(self):
        with tempfile.TemporaryDirectory() as folder:
            public = Path(folder) / 'public'
            (public / 'admin').mkdir(parents=True)
            (public / 'business').mkdir()
            admin = public / 'admin/negocios.php'
            admin.write_text("<?php require dirname(__DIR__,2).'/app/bootstrap.php';\n"
                             "require dirname(__DIR__,2).'/app/BusinessAdminService.php';")
            claim = public / 'reclamar-negocio.php'
            claim.write_text("<?php require __DIR__.'/../app/bootstrap.php';\n"
                             "require __DIR__.'/../app/BusinessClaimService.php';")

            deploy.rewrite_private_app_includes(public, self.config['remote_base'])

            self.assertEqual(admin.read_text(), "<?php require '/home/usuario/explorandolugares/app/bootstrap.php';\n"
                             "require '/home/usuario/explorandolugares/app/BusinessAdminService.php';")
            self.assertEqual(claim.read_text(), "<?php require '/home/usuario/explorandolugares/app/bootstrap.php';\n"
                             "require '/home/usuario/explorandolugares/app/BusinessClaimService.php';")

    def test_private_includes_accept_spacing_and_quote_styles(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            page = root / 'inversionistas.php'
            page.write_text("<?php require dirname(__DIR__, 2).'/app/bootstrap.php';\n"
                            'require dirname ( __DIR__ , 2 ) . "/app/InvestorBusinessIntelligence.php";\n'
                            'require __DIR__ . "/../app/BusinessPortalAccess.php";')
            deploy.rewrite_private_app_includes(root, self.config['remote_base'])
            text = page.read_text()
            self.assertNotIn('dirname', text)
            self.assertIn("require '/home/usuario/explorandolugares/app/bootstrap.php';", text)
            self.assertIn('require "/home/usuario/explorandolugares/app/InvestorBusinessIntelligence.php";', text)
            self.assertIn('require "/home/usuario/explorandolugares/app/BusinessPortalAccess.php";', text)

    def test_only_changed_hashes_are_selected(self):
        previous = {'files': [{'relative': 'public/index.html', 'sha256': 'old'},
                              {'relative': 'app/bootstrap.php', 'sha256': 'same'}]}
        current = [{'relative': 'public/index.html', 'sha256': 'new'},
                   {'relative': 'app/bootstrap.php', 'sha256': 'same'},
                   {'relative': 'public/new.css', 'sha256': 'new-file'}]
        self.assertEqual([item['relative'] for item in deploy.changed_files(previous, current)],
                         ['public/index.html', 'public/new.css'])

    def test_partial_scope_is_exact_and_rejects_missing_files(self):
        files = [{'relative': 'app/Business.php'}, {'relative': 'public/admin/negocios.php'},
                 {'relative': 'public/index.html'}]
        selected = deploy.select_files(files, ['app/Business.php', 'public/admin/negocios.php'])
        self.assertEqual([item['relative'] for item in selected],
                         ['app/Business.php', 'public/admin/negocios.php'])
        with self.assertRaises(ValueError):
            deploy.select_files(files, ['public/admin/missing.php'])

    def test_partial_manifest_preserves_hashes_of_untouched_files(self):
        previous = {'files': [
            {'relative': 'public/index.html', 'bytes': 10, 'sha256': 'a' * 64},
            {'relative': 'app/Old.php', 'bytes': 20, 'sha256': 'b' * 64},
        ]}
        current = [
            {'relative': 'public/index.html', 'bytes': 11, 'sha256': 'c' * 64},
            {'relative': 'app/Old.php', 'bytes': 20, 'sha256': 'd' * 64},
            {'relative': 'app/New.php', 'bytes': 30, 'sha256': 'e' * 64},
        ]
        manifest = deploy.create_partial_manifest(self.config, current, previous,
            [current[2]], 'abc123')
        entries = {item['relative']: item['sha256'] for item in manifest['files']}
        self.assertEqual(entries, {'public/index.html': 'a' * 64,
                                   'app/Old.php': 'b' * 64,
                                   'app/New.php': 'e' * 64})
        self.assertEqual(manifest['file_count'], 3)

    def test_direct_upload_is_incremental_backend_first_and_creates_nested_directories(self):
        files = [
            {'relative': 'public/business/register.php', 'local': '/tmp/register.php'},
            {'relative': 'app/Service.php', 'local': '/tmp/service.php'},
            {'relative': 'public/assets/leaflet/images/layers.png', 'local': '/tmp/layers.png'},
        ]
        commands = deploy.upload_commands({**self.config, 'public_root': '/home/usuario/public_html/explorandolugares.com'}, files)
        upload_commands = [command for command in commands if command.startswith('put ')]
        self.assertEqual(len(upload_commands), 3)
        self.assertIn('/tmp/service.php', upload_commands[0])
        self.assertLess(next(i for i, command in enumerate(upload_commands) if '/tmp/service.php' in command),
                        next(i for i, command in enumerate(upload_commands) if '/tmp/register.php' in command))
        self.assertIn('-mkdir "/home/usuario/public_html/explorandolugares.com/assets/leaflet/images"', commands)
        self.assertFalse(any(command.startswith(('rm ', 'rename ', 'get ')) for command in commands))

    def test_manifest_is_compact_and_full_deploy_records_clean_inventory(self):
        previous = {'files': [{'relative': 'public/old.html', 'sha256': 'a'}]}
        current = [{'relative': 'public/index.html', 'bytes': 12, 'sha256': 'b'}]
        manifest = deploy.create_manifest(self.config, current, previous, 'abc123')
        self.assertEqual(manifest['removed_not_deleted'], [])
        self.assertEqual(manifest['files'], current)
        self.assertNotIn('local', json.dumps(manifest))
        self.assertEqual(manifest['commit'], 'abc123')

    def test_only_previously_manifested_obsolete_files_are_removed(self):
        previous = {'files': [
            {'relative': 'public/assets/old.js', 'sha256': 'a' * 64},
            {'relative': 'app/OldService.php', 'sha256': 'b' * 64},
        ]}
        current = [{'relative': 'public/assets/new.js', 'sha256': 'c' * 64}]
        self.assertEqual(deploy.obsolete_files(previous, current), previous['files'])
        with self.assertRaises(ValueError):
            deploy.deployment_commands(self.config, [], '/tmp/manifest.json',
                                       [{'relative': 'public/../../outside'}])

    def test_manifest_rejects_another_project_or_path_traversal(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / 'manifest.json'
            path.write_text(json.dumps({'site': 'other', 'host': self.config['host'],
                                        'remote_base': self.config['remote_base'], 'files': []}))
            with self.assertRaises(ValueError):
                deploy.parse_manifest(path, self.config)
            path.write_text(json.dumps({'site': self.config['site'], 'host': self.config['host'],
                                        'remote_base': self.config['remote_base'],
                                        'files': [{'relative': 'public/../outside', 'sha256': 'a' * 64}]}))
            with self.assertRaises(ValueError):
                deploy.parse_manifest(path, self.config)

    def test_one_sftp_batch_uploads_changes_removes_old_managed_files_then_manifest(self):
        files = [{'relative': 'app/Service.php', 'local': '/tmp/service.php'},
                 {'relative': 'public/index.html', 'local': '/tmp/index.html'}]
        obsolete = [{'relative': 'public/assets/admin-accounts.js'}]
        commands = deploy.deployment_commands(self.config, files, '/tmp/new-manifest.json', obsolete)
        self.assertEqual(len([command for command in commands if command.startswith('put ')]), 3)
        self.assertLess(commands.index('put "/tmp/service.php" "/home/usuario/explorandolugares/app/Service.php"'),
                        commands.index('put "/tmp/index.html" "/home/usuario/explorandolugares/public/index.html"'))
        self.assertEqual(commands[-2], 'rm "/home/usuario/explorandolugares/public/assets/admin-accounts.js"')
        self.assertTrue(commands[-1].endswith('/' + deploy.MANIFEST_NAME + '"'))
        self.assertFalse(any(command.startswith(('get ', '-get ', 'rename ')) for command in commands))

    def test_strict_host_checking_remains_enabled(self):
        command = deploy.SFTP(self.config).command
        self.assertIn('-oStrictHostKeyChecking=yes', command)
        self.assertIn('-oBatchMode=yes', command)
        self.assertIn('-oForwardAgent=no', command)


if __name__ == '__main__':
    unittest.main()
