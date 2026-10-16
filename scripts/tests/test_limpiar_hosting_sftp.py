import importlib.util
from pathlib import Path
import unittest

MODULE = Path(__file__).resolve().parents[1] / 'limpiar-hosting-sftp.py'
spec = importlib.util.spec_from_file_location('cleanup', MODULE)
cleanup = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cleanup)


class CleanupTests(unittest.TestCase):
    def test_parses_only_files_and_directories(self):
        listing = '''sftp> ls -la "/tmp/release"
drwxrwxr-x    4 user user 4096 Oct 14 20:12 .
drwxrwxr-x    3 user user 4096 Oct 14 20:12 ..
drwxrwxr-x    4 user user 4096 Oct 14 20:12 app
-rw-r--r--    1 user user  123 Oct 14 20:12 index.html
'''
        self.assertEqual(cleanup.parse_listing(listing), [
            {'name': 'app', 'kind': 'directory', 'bytes': 4096},
            {'name': 'index.html', 'kind': 'file', 'bytes': 123},
        ])

    def test_rejects_ambiguous_names_and_does_not_treat_symlinks_as_files(self):
        with self.assertRaises(ValueError):
            cleanup.parse_listing('-rw-r--r-- 1 user user 10 Oct 14 20:12 nombre con espacios.txt')
        links = cleanup.parse_listing('lrwxrwxrwx 1 user user 10 Oct 14 20:12 current -> release')
        self.assertEqual(links[0]['kind'], 'other')

    def test_trash_tree_is_removed_bottom_up_and_only_within_selected_folder(self):
        class FakeSftp:
            def __init__(self):
                self.listings = {'/home/u/.el-trash/20261015T120000Z/release':
                                 'drwxr-xr-x 2 u u 4096 Oct 15 12:00 .\n-rw-r--r-- 1 u u 100 Oct 15 12:00 a.php\n'}

            def run(self, commands):
                return ''.join(f'sftp> {command}\n{self.listings[command.split(chr(34))[1]]}\n' for command in commands)

        commands, total_bytes = cleanup.tree_commands(FakeSftp(), '/home/u/.el-trash/20261015T120000Z/release')
        self.assertEqual(commands, [
            'rm "/home/u/.el-trash/20261015T120000Z/release/a.php"',
            'rmdir "/home/u/.el-trash/20261015T120000Z/release"',
        ])
        self.assertEqual(total_bytes, 100)

    def test_release_and_batch_ids_are_strict(self):
        self.assertRegex('20261015T030930Z-1409e3', cleanup.RELEASE_ID)
        self.assertRegex('20261015T030930Z', cleanup.BATCH_ID)
        self.assertIsNone(cleanup.RELEASE_ID.fullmatch('../public'))
        self.assertIsNone(cleanup.BATCH_ID.fullmatch('current'))


if __name__ == '__main__':
    unittest.main()
