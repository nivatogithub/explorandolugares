import unittest
from reuse_validation import original_data_preserved, unexpected_changes


class ValidationReuse(unittest.TestCase):
    def test_additive_content_keeps_old_records(self):
        self.assertTrue(original_data_preserved({'rows': [{'id': 1, 'name': 'Original'}]}, {'rows': [{'id': 1, 'name': 'Original'}, {'id': 2}]}))

    def test_overwrite_delete_and_reorder_are_rejected(self):
        for changed in [{'rows': []}, {'rows': [{'id': 1, 'name': 'Changed'}]}, {'other': 1}]:
            self.assertFalse(original_data_preserved({'rows': [{'id': 1, 'name': 'Original'}]}, changed))
        self.assertFalse(original_data_preserved([1, 2], [2, 1]))

    def test_code_change_is_never_reused(self):
        self.assertEqual(unexpected_changes({'backend/app/A.php': 'a'}, {'backend/app/A.php': 'b'}, set(), ['content/place/']), ['backend/app/A.php'])

    def test_only_exact_new_assets_and_scope_are_allowed(self):
        old = {'backend/public/old.webp': 'old'}
        new = {'backend/public/old.webp': 'changed', 'backend/public/new.webp': 'new', 'content/jamundi/a.json': 'a'}
        self.assertEqual(unexpected_changes(old, new, {'backend/public/new.webp'}, ['content/jamundi/']), ['backend/public/old.webp'])

    def test_another_place_is_not_in_scope(self):
        self.assertEqual(unexpected_changes({}, {'content/other/data.json': 'a'}, set(), ['content/jamundi/']), ['content/other/data.json'])


if __name__ == '__main__':
    unittest.main()
