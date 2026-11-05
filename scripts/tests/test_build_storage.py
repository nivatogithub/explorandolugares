import importlib.util
from pathlib import Path
import shutil
import signal
import subprocess
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('storage_under_test', ROOT/'scripts/build_storage.py')
storage = importlib.util.module_from_spec(spec)
spec.loader.exec_module(storage)
builder_path = next(p for p in (ROOT/'scripts/prepare-hosting.py', ROOT/'scripts/preparar-hosting.py') if p.exists())
spec = importlib.util.spec_from_file_location('builder_under_test', builder_path)
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class BuildStorageTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)/'worker'
        (self.root/'inputs').mkdir(parents=True)
        (self.root/'inputs/source.txt').write_text('unchanged')
        self.state = Path(self.temp.name)/'shared-state'

    def create_retained(self, name, root=None):
        root = root or self.root
        output = root/'.local'/name
        with storage.reserve_build(root, output, ['inputs'], state=self.state):
            output.mkdir(parents=True)
            (output/'test.txt').write_text(name)
        return output

    def test_quota_bounds_growth_without_deleting_reviewed_packages(self):
        kept = [self.create_retained(str(i)) for i in range(3)]
        with self.assertRaisesRegex(ValueError, 'máximo'):
            self.create_retained('fourth')
        self.assertEqual([p.joinpath('test.txt').read_text() for p in kept], ['0','1','2'])
        self.assertFalse((self.root/'.local/fourth').exists())

    def test_another_checkout_has_its_own_quota_and_unchanged_files(self):
        kept = [self.create_retained(str(i)) for i in range(3)]
        other = Path(self.temp.name)/'other-worker'
        (other/'inputs').mkdir(parents=True)
        self.create_retained('review', other)
        self.assertTrue(all(p.exists() for p in kept))

    def test_completed_disposable_test_leaves_no_package(self):
        with storage.temporary_package(self.root) as output:
            output.mkdir(); (output/'picture.webp').write_bytes(b'image')
            package = output
        self.assertFalse(package.parent.exists())

    def test_failed_test_does_not_remove_other_workers_files(self):
        protected = self.root/'.local/active-release'
        protected.mkdir(parents=True); (protected/'keep.txt').write_text('protected')
        with self.assertRaises(RuntimeError):
            with storage.temporary_package(self.root) as output:
                output.mkdir(); (output/'partial').write_text('temporary')
                raise RuntimeError('test failed')
        self.assertFalse(output.parent.exists())
        self.assertEqual((protected/'keep.txt').read_text(), 'protected')

    def test_low_disk_stops_before_writing_output_or_touching_sources(self):
        usage = shutil.disk_usage(self.root)._replace(free=storage.RESERVE_BYTES)
        with patch.object(storage.shutil, 'disk_usage', return_value=usage):
            with self.assertRaisesRegex(ValueError, 'Espacio insuficiente'):
                self.create_retained('no-space')
        self.assertFalse((self.root/'.local/no-space').exists())
        self.assertEqual((self.root/'inputs/source.txt').read_text(), 'unchanged')

    def test_lock_excludes_competing_builds_without_stopping_owner(self):
        with storage.exclusive_build(self.state):
            with self.assertRaisesRegex(ValueError, 'otra construcción'):
                with storage.exclusive_build(self.state, timeout=0):
                    self.fail('Competing build entered')
        with storage.exclusive_build(self.state, timeout=0):
            pass

    def test_deleted_own_test_does_not_keep_consuming_quota(self):
        deleted = self.create_retained('done')
        shutil.rmtree(deleted)
        for number in range(3):
            self.create_retained(str(number))

    def test_temporary_build_is_available_when_review_quota_is_full(self):
        for number in range(3):
            self.create_retained(str(number))
        with storage.temporary_package(self.root) as output:
            with storage.reserve_build(self.root, output, ['inputs'], retained=False, state=self.state):
                output.mkdir()
        rows = storage.read_registry(self.state/'retained.json')
        self.assertEqual(len(rows), 3)

    def test_failed_builder_removes_partial_output_not_empty_user_directory(self):
        destination = self.root/'.local/new-package'
        destination.mkdir(parents=True)
        def fail(output):
            output.mkdir(parents=True); (output/'half-built').write_text('partial')
            raise RuntimeError('build failed')
        with patch.object(builder, 'ROOT', self.root), patch.object(builder, 'INPUTS', ('inputs',)), \
             patch.object(builder, '_build_into', side_effect=fail):
            with self.assertRaises(RuntimeError):
                builder.build(destination)
        self.assertTrue(destination.is_dir())
        self.assertEqual(list(destination.iterdir()), [])
        self.assertFalse(list(destination.parent.glob('package-build-*')))

    def test_successful_builder_preserves_generated_bytes(self):
        destination = self.root/'.local/new-package'
        def create(output):
            output.mkdir(parents=True); (output/'picture.webp').write_bytes(b'approved bytes')
        with patch.object(builder, 'ROOT', self.root), patch.object(builder, 'INPUTS', ('inputs',)), \
             patch.object(builder, '_build_into', side_effect=create):
            builder.build(destination)
        self.assertEqual((destination/'picture.webp').read_bytes(), b'approved bytes')
        self.assertFalse(list(destination.parent.glob('package-build-*')))

    def test_runner_propagates_failure_and_cleans_its_package(self):
        # A tiny real child invocation tests CLI argument handling and cleanup,
        # without building a full portal for every assertion.
        repo = self.root
        (repo/'scripts').mkdir()
        for name in ('run-test-package.py','build_storage.py'):
            shutil.copyfile(ROOT/'scripts'/name, repo/'scripts'/name)
        (repo/'scripts/prepare-hosting.py').write_text(
            'def build(path, *, retained=True):\n'
            ' path.mkdir(parents=True)\n (path/"ready").write_text("yes")\n')
        evidence = repo/'seen-path.txt'
        script = 'from pathlib import Path; import sys; p=Path(sys.argv[1]); assert (p/"ready").read_text()=="yes"; Path(sys.argv[2]).write_text(str(p)); sys.exit(7)'
        result = subprocess.run([sys.executable, str(repo/'scripts/run-test-package.py'), '--',
                                 sys.executable, '-c', script, '{package}', str(evidence)], capture_output=True)
        self.assertEqual(result.returncode, 7, result.stderr)
        self.assertFalse(Path(evidence.read_text()).exists())

    def test_interrupted_runner_stops_only_its_child_before_cleanup(self):
        repo = self.root
        (repo/'scripts').mkdir()
        for name in ('run-test-package.py','build_storage.py'):
            shutil.copyfile(ROOT/'scripts'/name, repo/'scripts'/name)
        (repo/'scripts/prepare-hosting.py').write_text(
            'def build(path, *, retained=True):\n path.mkdir(parents=True)\n')
        evidence = repo/'child.json'
        script = 'import json,os,sys,time; from pathlib import Path; Path(sys.argv[2]).write_text(json.dumps({"package":sys.argv[1],"pid":os.getpid()})); time.sleep(30)'
        process = subprocess.Popen([sys.executable, str(repo/'scripts/run-test-package.py'), '--',
                                    sys.executable, '-c', script, '{package}', str(evidence)],
                                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            deadline = time.monotonic()+5
            while not evidence.exists() and time.monotonic()<deadline:
                time.sleep(0.01)
            self.assertTrue(evidence.exists())
            import json, os
            row=json.loads(evidence.read_text())
            process.send_signal(signal.SIGTERM)
            self.assertEqual(process.wait(timeout=5),130)
            self.assertFalse(Path(row['package']).parent.exists())
            with self.assertRaises(ProcessLookupError):
                os.kill(row['pid'],0)
        finally:
            if process.poll() is None:
                process.kill(); process.wait()


if __name__ == '__main__':
    unittest.main()
