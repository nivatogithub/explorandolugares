from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]


class PlaceQueryBoundary(unittest.TestCase):
    def test_sql_does_not_escape_selectors_in_the_public_place_flow(self):
        paths = ['backend/app/PlaceRepository.php',
                 'backend/app/HomeAttractions.php', 'backend/integration-analytics/public/place.php',
                 'backend/integration-analytics/public/places.php', 'backend/app/views/place-main.php',
                 'backend/app/PlaceDirectoryService.php', 'backend/app/views/place-directory.php']
        for name in paths:
            self.assertIsNone(re.search(r'->\s*(prepare|query|exec)\s*\(', (ROOT / name).read_text()), name)

    def test_claim_selector_only_reads_and_has_no_http_state(self):
        selector = (ROOT / 'backend/app/BusinessClaimSelector.php').read_text()
        for term in ['$_GET', '$_POST', '$_SESSION', 'beginTransaction(', 'commit(', 'rollBack(']:
            self.assertNotIn(term, selector)
        self.assertIsNone(re.search(r"[\'\"]\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b", selector))
        service = (ROOT / 'backend/app/BusinessClaimService.php').read_text()
        for name in ['places', 'categories', 'search', 'requests']:
            self.assertRegex(service, r'->' + name + r'\(')
        self.assertIn('beginTransaction()', service)
        self.assertIn('rollBack()', service)
        self.assertIsNone(re.search(r'->\s*(prepare|query|exec|lastInsertId)\s*\(',service))
        writer=(ROOT/'backend/app/BusinessClaimWriter.php').read_text()
        for term in ['$_POST', '$_SESSION', 'beginTransaction(', 'commit(', 'rollBack(']:
            self.assertNotIn(term,writer)
        self.assertLess(writer.index('lastInsertId()'),writer.index('UPDATE businesses'))

    def test_obsolete_repository_is_removed(self):
        self.assertFalse((ROOT / 'backend/app/DestinationRepository.php').exists())

    def test_place_media_queries_exclude_private_columns(self):
        source = (ROOT / 'backend/app/PlaceSelector.php').read_text()
        for term in ['provenance_json', 'internal_research_json', 'm.*', 'SELECT *']:
            self.assertNotIn(term, source)

    def test_read_selectors_do_not_write_entities(self):
        for name in ['PlaceSelector.php', 'PlaceDirectorySelector.php', 'PlaceAdminSelector.php', 'BusinessDirectorySelector.php', 'ExperienceOperatorSelector.php']:
            source = (ROOT / 'backend/app' / name).read_text()
            self.assertIsNone(re.search(r'[\'\"]\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP)\b', source), name)

    def test_private_place_queries_are_kept_out_of_public_service(self):
        self.assertNotIn('PlaceAdminSelector', (ROOT / 'backend/app/PlaceRepository.php').read_text())
        admin = (ROOT / 'backend/app/PlaceAdminService.php').read_text()
        self.assertIsNone(re.search(r'[\'\"]\s*SELECT\b', admin))
        self.assertIn('PlaceAdminSelector', admin)

    def test_new_public_consumers_use_lugares_service(self):
        for name, service in [('place.php', 'PlaceRepository::fromDatabase'), ('places.php', 'PlaceDirectoryService')]:
            source = (ROOT / 'backend/integration-analytics/public' / name).read_text()
            self.assertIn(service, source)
            self.assertNotIn('DestinationRepository', source)

    def test_directory_filters_and_pagination_are_server_rendered(self):
        controller=(ROOT/'backend/integration-analytics/public/places.php').read_text()
        self.assertNotIn('<!doctype html>',controller)
        template=(ROOT/'backend/app/views/place-directory.php').read_text()
        for term in ['$_GET','$_POST','$_SESSION','->prepare(', '->query(', '->exec(', 'PlaceDirectorySelector', 'preg_replace(', 'DOMDocument']:
            self.assertNotIn(term,template)
        self.assertIn('method="get"',template)
        self.assertIn('rel="next"',template)
        service=(ROOT/'backend/app/PlaceDirectoryService.php').read_text()
        for term in ['PDO','$_GET','$_SESSION','<html','header(']:self.assertNotIn(term,service)
        selector=(ROOT/'backend/app/PlaceDirectorySelector.php').read_text()
        self.assertIn('LIMIT ? OFFSET ?',selector)
        for term in ['provenance_json','internal_research_json','SELECT *']:self.assertNotIn(term,selector)

    def test_publication_policy_has_no_transport_or_storage_dependencies(self):
        source=(ROOT/'backend/app/PlacePublicationPolicy.php').read_text()
        for dependency in ['PDO','$_GET','$_POST','$_SESSION','$_SERVER','header(',
                           'file_get_contents(', 'file_put_contents(', 'move_uploaded_file(']:
            self.assertNotIn(dependency,source)

    def test_place_image_storage_is_independent_of_database_and_http(self):
        source=(ROOT/'backend/app/PlaceImageStorage.php').read_text()
        for dependency in ['PDO','$_GET','$_POST','$_SESSION','$_FILES','header(']:
            self.assertNotIn(dependency,source)
        service=(ROOT/'backend/app/PlaceAdminService.php').read_text()
        self.assertIsNone(re.search(r'\b(?:is_uploaded_file|move_uploaded_file|mkdir|unlink|getimagesize|fopen|fwrite|file_put_contents)\s*\(',service))

    def test_place_writes_and_transaction_have_separate_owners(self):
        service=(ROOT/'backend/app/PlaceAdminService.php').read_text()
        self.assertIsNone(re.search(r'->\s*(prepare|query|exec|lastInsertId)\s*\(',service))
        self.assertIn('PlaceWriter',service)
        writer=(ROOT/'backend/app/PlaceWriter.php').read_text()
        for term in ['beginTransaction(', 'commit(', 'rollBack(', '$_POST', '$_FILES', '$_SESSION',
                     'PlacePublicationPolicy', 'move_uploaded_file(', 'header(']:
            self.assertNotIn(term,writer)
        self.assertIsNone(re.search(r'[\'\"]\s*SELECT\b',writer))

    def test_place_reader_contract_keeps_infrastructure_out(self):
        contract=(ROOT/'backend/app/PlaceReader.php').read_text()
        for term in ['PDO', '$_GET', '$_POST', '$_SESSION', 'header(', 'SELECT ', 'INSERT ',
                     'destination_translations', 'move_uploaded_file(']:
            self.assertNotIn(term,contract)
        repository=(ROOT/'backend/app/PlaceRepository.php').read_text()
        self.assertIn('private PlaceReader $places',repository)
        self.assertNotIn('private PlaceSelector $places',repository)
        self.assertIn('implements PlaceReader',(ROOT/'backend/app/PlaceSelector.php').read_text())

    def test_place_admin_template_only_receives_presentation_data(self):
        template=(ROOT/'backend/app/views/admin/places.php').read_text()
        for term in ['$_GET','$_POST','$_FILES','$_SESSION','$service','PlaceAdminService','->prepare(',
                     '->query(', '->exec(', 'db(', 'header(', 'http_response_code(']:
            self.assertNotIn(term,template)
        controller=(ROOT/'backend/public/admin/places.php').read_text()
        self.assertNotIn('<!doctype html>',controller)
        self.assertNotIn('function placeFields(',controller)
        self.assertIn('csrf();',controller)
        self.assertIn('EXTR_SKIP',controller)


if __name__ == '__main__':
    unittest.main()
