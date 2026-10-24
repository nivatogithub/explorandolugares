"""Administration endpoints must share one session boundary."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]

class AdminSessionBoundaryTests(unittest.TestCase):
    def test_bootstrap_protects_the_administration_namespace(self):
        source=(ROOT/'backend/app/bootstrap.php').read_text()
        self.assertIn('function enforce_administration_session()',source)
        self.assertIn("str_starts_with($path,'/admin/')",source)
        self.assertIn("header('Location: '.site_path('/negocios/acceso/')",source)

    def test_configuration_has_no_local_403_login_message(self):
        source=(ROOT/'backend/public/admin/configuracion.php').read_text()
        self.assertIn('require_admin_session();',source)
        self.assertNotIn('Inicia sesión en administración.',source)

    def test_business_private_entries_share_the_account_guard(self):
        for name in ['dashboard.php','checkout.php','editorial.php','editorial-image.php']:
            source=(ROOT/'backend/public/business'/name).read_text()
            self.assertIn('require_business_session()',source,name)

    def test_expired_account_api_response_has_a_login_target(self):
        source=(ROOT/'backend/app/bootstrap.php').read_text()
        self.assertIn("'error'=>'authentication_required'",source)
        self.assertIn("'login_url'=>site_path('/negocios/acceso/')",source)

if __name__=='__main__':
    unittest.main()
