"""Administration endpoints must share one session boundary."""
from pathlib import Path
import unittest
import re

ROOT=Path(__file__).resolve().parents[2]

class AdminSessionBoundaryTests(unittest.TestCase):
    def test_business_demo_does_not_rewrite_html(self):
        root=Path(__file__).resolve().parents[2]
        source=(root/'backend/public/business/demo.php').read_text()
        for term in ['ob_start(', 'preg_replace(', 'preg_replace_callback(', 'DOMDocument']:
            self.assertNotIn(term,source)
        self.assertIn("$business['disable_contact_links']=true",source)

    def test_commercial_registration_reads_use_selector(self):
        root=Path(__file__).resolve().parents[2]
        controller=(root/'backend/public/business/register.php').read_text()
        selector=(root/'backend/app/BusinessRegistrationSelector.php').read_text()
        self.assertNotIn('->prepare(',controller)
        self.assertIn('BusinessRegistrationSelector',controller)
        for term in ['$_GET','$_POST','$_SESSION','INSERT ', 'UPDATE ', 'DELETE ']:
            self.assertNotIn(term,selector)

    def test_operation_reports_separate_queries_and_view(self):
        root=Path(__file__).resolve().parents[2]
        controller=(root/'backend/public/admin/reports.php').read_text()
        selector=(root/'backend/app/OperationReportSelector.php').read_text()
        view=(root/'backend/app/views/admin/reports.php').read_text()
        self.assertIn('require_admin_session()',controller)
        self.assertNotIn('->query(',controller)
        self.assertNotIn('<!doctype',controller.lower())
        for term in ['$_GET','$_POST','$_SESSION','PDO','->query(', '->prepare(']:
            self.assertNotIn(term,view)
        for term in ['$_GET','$_POST','$_SESSION','INSERT ', 'UPDATE ', 'DELETE ']:
            self.assertNotIn(term,selector)

    def test_claim_view_has_explicit_boundaries(self):
        root = Path(__file__).resolve().parents[2]
        controller=(root/'backend/public/business-claim.php').read_text()
        view=(root/'backend/app/views/account/business-claim.php').read_text()
        self.assertNotIn('_account-page.php', controller)
        self.assertNotIn('<!doctype', controller.lower())
        self.assertIn('csrf();', controller)
        for term in ['$_GET', '$_POST', '$_SESSION', 'PDO', '->prepare(', '->query(', 'preg_replace(', 'DOMDocument', 'ob_start(']:
            self.assertNotIn(term, view)
        self.assertFalse((root/'backend/public/_account-page.php').exists())
        presentation=(root/'backend/app/PortalPresentation.php').read_text()
        self.assertNotIn('function translateAccount(', presentation)
        self.assertNotIn('function injectAssets(', presentation)

    def test_bootstrap_protects_the_administration_namespace(self):
        source=(ROOT/'backend/app/bootstrap.php').read_text()
        self.assertIn('function enforce_administration_session()',source)
        self.assertIn("str_starts_with($path,'/admin/')",source)
        self.assertIn("header('Location: '.site_path('/negocios/acceso/')",source)

    def test_configuration_has_no_local_403_login_message(self):
        source=(ROOT/'backend/public/admin/settings.php').read_text()
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

    def test_preview_and_admin_share_account_validation(self):
        for name in ['backend/app/bootstrap.php','backend/integration-analytics/public/place.php']:
            source=(ROOT/name).read_text()
            self.assertIn('AccountSessionValidator::synchronize(',source,name)
            self.assertNotIn('AccountAuthorization::synchronize(',source,name)
        preview=(ROOT/'backend/integration-analytics/public/place.php').read_text()
        self.assertLess(preview.index('AccountSessionValidator::synchronize('),preview.index('PlaceRepository::fromDatabase('))
        validator=(ROOT/'backend/app/AccountSessionValidator.php').read_text()
        self.assertNotIn('$_SERVER',validator)
        self.assertNotIn('header(',validator)

    def test_admin_layout_only_uses_prepared_context(self):
        for name in ['_navigation.php','_admin-layout.php']:
            source=(ROOT/'backend/public/admin'/name).read_text()
            for term in ['$_GET','$_POST','$_SESSION','$_SERVER','db(', '->prepare(', '->query(', '->exec(']:
                self.assertNotIn(term,source,name)
        context=(ROOT/'backend/app/AdminLayoutContext.php').read_text()
        for term in ['PDO','$_GET','$_SESSION','$_SERVER','header(', 'db(']:
            self.assertNotIn(term,context)
        self.assertIn('SELECT display_name FROM accounts WHERE id=?',(ROOT/'backend/app/AdminAccountSelector.php').read_text())

    def test_every_private_navigation_entry_prepares_its_context(self):
        for path in (ROOT/'backend/public/admin').glob('*.php'):
            source=path.read_text()
            if '_navigation.php' in source:
                self.assertIn('admin_layout_context();',source,path.name)
        controller=(ROOT/'backend/public/admin/places.php').read_text()
        self.assertIn("$viewData['adminLayout']=admin_layout_context();",controller)
        self.assertIn("admin_layout_account($adminLayout['account'])",(ROOT/'backend/public/admin/_navigation.php').read_text())

    def test_shared_editor_admin_layout_is_explicit(self):
        source=(ROOT/'backend/public/business/editorial.php').read_text()
        self.assertFalse((ROOT/'backend/public/admin/_page.php').exists())
        for term in ['admin_page(', 'account_page(', '_account-page.php', '_account-header.php', 'ob_start(', 'preg_replace(', 'preg_replace_callback(', 'translateAccount(', 'injectAssets(']:
            self.assertNotIn(term,source)
        for term in ["$adminLayout=admin_layout_context();", "$accountLayout=account_layout_context();", "app/views/business/editorial.php"]:
            self.assertIn(term,source)
        view=(ROOT/'backend/app/views/business/editorial.php').read_text()
        for term in ["PortalPresentation::assets(!$admin,$admin?'admin':'public',$admin?'all':'js')", "'portal-admin':'portal-account-page'", "admin/_navigation.php", 'PortalPresentation::header($accountLayout)', 'PortalPresentation::footer($accountLayout)']:
            self.assertIn(term,view)
        for term in ['$_GET','$_POST','$_SESSION','db()','PDO']:
            self.assertNotIn(term,view)
        navigation=(ROOT/'backend/public/admin/_navigation.php').read_text()
        for href in re.findall(r'href="([^"]+)"',navigation):
            self.assertTrue(href.startswith(('/admin/','<?=esc(')),href)

    def test_business_gate_is_explicit(self):
        source=(ROOT/'backend/public/business/register.php').read_text()
        self.assertIn("AccountAuthPresentation::render('business-account-gate'",source)
        self.assertNotIn("require __DIR__.'/business-account-gate.php'",source)
        self.assertFalse((ROOT/'backend/public/business/business-account-gate.php').exists())
        gate=source.split('if(!$currentAccount){',1)[1].split("if(empty($currentAccount['email_verified_at']))",1)[0]
        self.assertNotIn('account_page(',gate)

    def test_profile_only_renders_prepared_data(self):
        controller=(ROOT/'backend/public/business/traveler.php').read_text()
        self.assertIn('AccountProfilePresentation::render(',controller)
        for term in ['account_page(', '<!doctype', 'translateAccount(', 'injectAssets(']:
            self.assertNotIn(term,controller)
        presenter=(ROOT/'backend/app/AccountProfilePresentation.php').read_text()
        for term in ['$_GET','$_POST','$_SESSION','$_SERVER','db(', 'PDO', '->prepare(', 'preg_replace(', 'DOMDocument', 'translateAccount(', 'injectAssets(']:
            self.assertNotIn(term,presenter)

    def test_svg_comparison_accepts_empty_tag_serialization_and_preserves_structure(self):
        from importlib.util import spec_from_file_location,module_from_spec
        spec=spec_from_file_location('editor_markup',ROOT/'scripts/check-editorial-layout.py')
        module=module_from_spec(spec);spec.loader.exec_module(module)
        samples=['<svg><rect x="1"/><path d="M1 1"/></svg>',
                 '<svg><rect x="1"></rect><path d="M1 1"></path></svg>',
                 '<svg><rect x="1"><path d="M1 1"></path></rect></svg>']
        results=[]
        for sample in samples:
            parser=module.EditorMarkup(ROOT);parser.feed(sample);results.append(parser.events)
        self.assertEqual(results[0],results[1])
        self.assertNotEqual(results[0],results[2])

    def test_workspace_only_renders_prepared_data(self):
        source=(ROOT/'backend/public/business/dashboard.php').read_text()
        self.assertIn('BusinessWorkspacePresentation::render(',source)
        for term in ['account_page(', '<!doctype', '_account-header.php', 'ob_start(', 'preg_replace(']:
            self.assertNotIn(term,source)
        presenter=(ROOT/'backend/app/BusinessWorkspacePresentation.php').read_text()
        view=(ROOT/'backend/app/views/account/business-workspace.php').read_text()
        for term in ['$_GET','$_POST','$_SESSION','$_SERVER','db(', 'PDO', 'BusinessQrCode::', 'BusinessEditorialService::', 'BusinessWorkspace::', '->prepare(', 'preg_replace(', 'DOMDocument', 'translateAccount(', 'injectAssets(']:
            self.assertNotIn(term,presenter)
            self.assertNotIn(term,view)
        for name in ['_workspace-icon.php','qr-dialog.php','profile-logout.php']:
            self.assertFalse((ROOT/'backend/public/business'/name).exists())

    def test_commercial_registration_uses_explicit_private_views(self):
        controller=(ROOT/'backend/public/business/register.php').read_text()
        self.assertIn('BusinessRegistrationPresentation::render(',controller)
        for term in ['account_page(', '<!doctype', '_account-header.php', 'ob_start(']:
            self.assertNotIn(term,controller)
        paths=[ROOT/'backend/app/BusinessRegistrationPresentation.php',ROOT/'backend/app/BusinessPhonePresentation.php']
        paths+=list((ROOT/'backend/app/views/account').glob('business-registration*.php'))
        paths+=[ROOT/'backend/app/views/account/business-phone.php',ROOT/'backend/app/views/account/business-map-dialog.php']
        for path in paths:
            for term in ['$_GET','$_POST','$_SESSION','$_SERVER','db(', 'PDO', '->prepare(', '->query(', 'DOMDocument', 'translateAccount(', 'injectAssets(', 'ob_start(']:
                self.assertNotIn(term,path.read_text(),str(path))
        for name in ['new-business-fields.php','subscription-choice.php','operator-addon-choice.php']:
            self.assertFalse((ROOT/'backend/public/business'/name).exists())

    def test_business_selection_only_renders_prepared_data(self):
        controller=(ROOT/'backend/public/business/checkout.php').read_text()
        self.assertIn('BusinessSelectionPresentation::render(',controller)
        for term in ['account_page(', '<!doctype', '_account-header.php', 'ob_start(']:
            self.assertNotIn(term,controller)
        for relative in ['backend/app/BusinessSelectionPresentation.php','backend/app/views/account/business-selection.php']:
            source=(ROOT/relative).read_text()
            for term in ['$_GET','$_POST','$_SESSION','$_SERVER','db(', 'PDO', 'BusinessSubscriptionChoice::', 'SubscriptionPurchaseIntent::', '->prepare(', '->query(', 'DOMDocument', 'preg_replace(', 'translateAccount(', 'injectAssets(', 'ob_start(']:
                self.assertNotIn(term,source,relative)
        view=(ROOT/'backend/app/views/account/business-selection.php').read_text()
        self.assertIn('/negocios-locales/suscripciones/',view)

    def test_grouped_payment_view_and_service_boundaries(self):
        controller=(ROOT/'backend/public/business/payment.php').read_text()
        for term in ['account_page(', '_account-page.php', '<!doctype', '->prepare(', '->query(']:
            self.assertNotIn(term,controller)
        self.assertIn('BusinessPaymentPresentation::render(',controller)
        for name in ['BusinessPaymentPresentation.php','views/account/business-payment.php','views/account/payment-instructions.php','views/account/payment-publication-terms.php']:
            source=(ROOT/'backend/app'/name).read_text()
            for term in ['$_POST','$_GET','$_SESSION','$_SERVER','PDO','db(', '->prepare(', '->query(', 'DOMDocument','preg_replace(', 'ob_start(', 'BusinessPaymentOrderService::']:
                self.assertNotIn(term,source,name)
        source=(ROOT/'backend/app/BusinessPaymentOrderService.php').read_text()
        for term in ['$_POST','$_FILES','$_SESSION','->prepare(', '->query(', '->exec(', 'move_uploaded_file(']:
            self.assertNotIn(term,source)
        storage=(ROOT/'backend/app/BusinessPaymentUpload.php').read_text()
        self.assertIn('implements PaymentEvidenceStorage',storage)
        self.assertNotIn('PDO',storage)

    def test_account_auth_views_receive_prepared_data(self):
        for page in ['login','recover','password','registration-choice','verify-email']:
            controller=(ROOT/'backend/public/business'/f'{page}.php').read_text()
            view='personal-registration' if page=='registration-choice' else page
            self.assertIn("AccountAuthPresentation::render('"+view+"'",controller)
            self.assertNotIn('account_page(',controller)
            self.assertNotIn('<!doctype',controller)
        paths=list((ROOT/'backend/app/views/account').glob('*.php'))+[ROOT/'backend/app/views/home-main.php',ROOT/'backend/app/AccountAuthPresentation.php',ROOT/'backend/app/AccountResidencePresentation.php']
        for path in paths:
            source=path.read_text()
            for term in ['$_GET','$_POST','$_SESSION','$_SERVER','db(', 'PDO', '->prepare(', 'preg_replace(', 'DOMDocument', 'translateAccount(', 'injectAssets(']:
                self.assertNotIn(term,source,path.name)

    def test_contact_view_uses_explicit_data(self):
        controller=(ROOT/'backend/public/contact.php').read_text()
        self.assertIn('ContactPresentation::render(',controller)
        for term in ['account_page(', '_account-page.php', '_account-header.php', '<!doctype', 'ob_start(']:
            self.assertNotIn(term,controller)
        for name in ['ContactPresentation.php','views/account/contact.php']:
            source=(ROOT/'backend/app'/name).read_text()
            for term in ['$_GET','$_POST','$_SESSION','$_SERVER','PDO','db(', '->prepare(', 'DOMDocument', 'translateAccount(', 'injectAssets(', 'ob_start(']:
                self.assertNotIn(term,source,name)

if __name__=='__main__':
    unittest.main()
