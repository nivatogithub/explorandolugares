"""Native regression normalization must retain full link destinations without XML entity loss."""
from pathlib import Path
import json,subprocess,unittest
ROOT=Path(__file__).resolve().parents[2]
class HttpMarkupComparisonTests(unittest.TestCase):
    def compare(self,html):
        auth=(ROOT/'dev/check-account-auth-http.php').read_text().split('function auth_http_path',1)[1].split('\nlayout_http_session(',1)[0]
        profile=(ROOT/'dev/check-account-profile-http.php').read_text().split('function profile_http_markup',1)[1].split('\nlayout_http_session(',1)[0]
        code='function auth_http_path'+auth+'\nfunction profile_http_markup'+profile+r'''
$i=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
[$xpath,$digest]=profile_http_markup($i['html'],'synthetic-token','/business/checkout.php?lang=es');
$values=[];foreach($xpath->query('//@href | //@action') as $attribute)$values[]=$attribute->value;
echo json_encode(['values'=>$values,'digest'=>$digest],JSON_THROW_ON_ERROR);
'''
        result=subprocess.run(['php','-d','display_errors=stderr','-r',code],input=json.dumps({'html':html}),text=True,capture_output=True,check=True)
        self.assertFalse(result.stderr,result.stderr)
        return json.loads(result.stdout)
    def test_multiple_query_parameters_survive(self):
        result=self.compare('<a href="/negocios/registro/?tipo=business&amp;desde=simulador">Crear</a>')
        self.assertEqual(result['values'],['/negocios/registro/?tipo=business&desde=simulador'])
    def test_query_only_link_keeps_current_file(self):
        result=self.compare('<a href="?business=42">Elegir</a><form action="../business/checkout.php?plan=page&amp;period=annual"></form>')
        self.assertEqual(result['values'],['/business/checkout.php?business=42','/business/checkout.php?plan=page&period=annual'])
    def test_changed_query_is_not_normalized_away(self):
        first=self.compare('<a href="/negocios/registro/?tipo=business&amp;desde=simulador">Crear</a>')
        second=self.compare('<a href="/negocios/registro/?tipo=business&amp;desde=otro">Crear</a>')
        self.assertNotEqual(first['digest'],second['digest'])
if __name__=='__main__':unittest.main()
