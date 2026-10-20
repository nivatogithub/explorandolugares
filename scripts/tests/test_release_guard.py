import copy
import sys
from pathlib import Path
import unittest
from unittest.mock import patch
import subprocess

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from release_guard import require_complete_delta, require_package_parity, require_validation, manifest_digest, require_main_ancestor, require_no_missing_files


class ReleaseGuardTests(unittest.TestCase):
    def setUp(self):
        self.files = [{'relative':'public/index.html','sha256':'a'*64}, {'relative':'public/cobertura/colombia/index.html','sha256':'b'*64}]
        self.manifest = {'commit':'abc1234','files':copy.deepcopy(self.files)}
        self.report = {'schema_version':1,'commit':'abc1234','manifest_sha256':manifest_digest(self.manifest),'checks':{k:True for k in ['public_pages','accounts','statistics','assets','database']}}

    def test_partial_release_cannot_hide_pending_coverage(self):
        with self.assertRaisesRegex(ValueError, 'cobertura'):
            require_complete_delta(self.files, self.files[:1])

    def test_exact_delta_can_publish_incrementally(self):
        require_complete_delta(self.files, self.files)

    def test_unchanged_files_need_not_be_transferred(self):
        require_complete_delta(self.files[:1], self.files[:1])

    def test_full_inventory_must_match(self):
        require_package_parity(self.files, self.manifest)
        for files in [self.files[:1], self.files+[{'relative':'app/new.php','sha256':'c'*64}], [dict(self.files[0],sha256='c'*64),self.files[1]]]:
            with self.assertRaises(ValueError): require_package_parity(files,self.manifest)

    def test_duplicate_inventory_is_rejected(self):
        with self.assertRaises(ValueError):require_package_parity(self.files+self.files,self.manifest)

    def test_partial_flag_cannot_hide_missing_published_file(self):
        with self.assertRaises(ValueError):require_no_missing_files(self.files[:1],self.manifest)
        require_no_missing_files(self.files,self.manifest)

    def test_validation_is_bound_to_manifest_and_commit(self):
        require_validation(self.manifest, self.report)
        for key,value in [('commit','another'),('manifest_sha256','wrong'),('schema_version',2)]:
            bad=dict(self.report);bad[key]=value
            with self.assertRaises(ValueError):require_validation(self.manifest,bad)

    def test_missing_or_failed_functional_checks_block_main(self):
        for key in self.report['checks']:
            for value in [False, None]:
                bad=copy.deepcopy(self.report)
                if value is None:del bad['checks'][key]
                else:bad['checks'][key]=value
                with self.assertRaises(ValueError):require_validation(self.manifest,bad)

    def test_divergent_main_blocks_deployment(self):
        responses=[subprocess.CompletedProcess([],0),subprocess.CompletedProcess([],1)]
        with patch('release_guard.subprocess.run',side_effect=responses):
            with self.assertRaises(ValueError):require_main_ancestor(Path('.'))

    def test_integrated_main_is_accepted(self):
        with patch('release_guard.subprocess.run',return_value=subprocess.CompletedProcess([],0)):
            require_main_ancestor(Path('.'))


if __name__=='__main__':unittest.main()
