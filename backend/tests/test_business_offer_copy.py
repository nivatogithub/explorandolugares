"""Customer-facing claims must match the subscription catalogue and publication flow."""
from pathlib import Path
from html.parser import HTMLParser
import re,sqlite3,unittest
ROOT=Path(__file__).resolve().parents[2]
class Copy(HTMLParser):
 def __init__(self):super().__init__();self.rows=[];self.row=None;self.cell=None;self.text=[]
 def handle_starttag(self,tag,attrs):
  if tag=='tr':self.row=[]
  if tag in ('th','td') and self.row is not None:self.cell=[]
 def handle_data(self,s):
  self.text.append(s)
  if self.cell is not None:self.cell.append(s)
 def handle_endtag(self,tag):
  if tag in ('th','td') and self.cell is not None:self.row.append(' '.join(''.join(self.cell).split()));self.cell=None
  if tag=='tr' and self.row is not None:self.rows.append(self.row);self.row=None
class OfferTests(unittest.TestCase):
 def test_catalogue_capacities_are_characters_in_all_languages(self):
  db=sqlite3.connect(':memory:');db.executescript("CREATE TABLE subscription_plans(code TEXT PRIMARY KEY);INSERT INTO subscription_plans VALUES('free'),('basic'),('featured'),('page'),('page_premium');")
  db.executescript((ROOT/'backend/database/migrations/016-plan-content-limits.sqlite.sql').read_text())
  db.executescript((ROOT/'backend/database/migrations/053-current-business-text-rules.sqlite.sql').read_text())
  db.executescript((ROOT/'backend/database/migrations/054-business-description-budgets.sqlite.sql').read_text())
  # The approved presentation omits history and the old services text; those
  # legacy storage limits are not advertised as visible page sections.
  limits=[set(v for v in db.execute('SELECT max_summary_chars,max_description_chars FROM subscription_plans WHERE code=?',[p]).fetchone() if v) for p in ('featured','page','page_premium')];db.close()
  for lang,label in [('', 'Capacidad de información'),('en/','Content capacity'),('pt-br/','Capacidade de conteúdo')]:
   with self.subTest(lang=lang):
    p=Copy();p.feed((ROOT/'ensayos/v1'/lang/'negocios-locales/planes/index.html').read_text());rows=[r for r in p.rows if len(r)==5 and r[0]==label];self.assertEqual(len(rows),1)
    for cell,expected in zip(rows[0][2:],limits):
     self.assertRegex(cell,r'caracter|character');self.assertEqual(set(int(re.sub('[.,]','',v)) for v in re.findall(r'\d[\d.,]*',cell)),expected)
 def test_current_offer_and_terms_survive_in_all_languages(self):
  for lang,banned in [('',r'Aún no son contratables|Modalidades futuras|distintivo no cambia el orden'),('en/',r'not yet available to purchase|Future options|badge does not change matching order'),('pt-br/',r'Ainda não são contratáveis|Modalidades futuras|selo não altera a ordem')]:
   for page in ['index.html','planes/index.html','proceso/index.html']:
    with self.subTest(lang=lang,page=page):
     s=(ROOT/'ensayos/v1'/lang/'negocios-locales'/page).read_text();self.assertNotRegex(s,banned)
     if page!='index.html':
      self.assertEqual(s.count('id="condiciones-de-publicacion"'),1)
      self.assertIn('(5)',s);self.assertNotRegex(s,r'retracto|right of withdrawal|direito legal de arrependimento')
 def test_conditions_precede_payment_instructions(self):
  s=(ROOT/'backend/public/business/payment-instructions.php').read_text();self.assertLess(s.index("require __DIR__.'/publication-terms.php'"),s.index('<h4>Cómo realizar el pago'))
  terms=(ROOT/'backend/public/business/publication-terms.php').read_text();self.assertIn('cinco (5) días hábiles después de recibir el pago',terms);self.assertNotIn('retracto',terms.lower())
if __name__=='__main__':unittest.main()
