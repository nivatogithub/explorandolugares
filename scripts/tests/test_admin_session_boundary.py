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

if __name__=='__main__':
    unittest.main()
