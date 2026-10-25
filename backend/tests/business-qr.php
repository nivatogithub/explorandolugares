<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessQrCode.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach(['sqlite.sql','destinations.sqlite.sql','migrations/006-poi-experience-links.sqlite.sql'] as $f)$p->exec(file_get_contents(dirname(__DIR__).'/database/'.$f));
$files=glob(dirname(__DIR__).'/database/migrations/*.sqlite.sql');sort($files);foreach($files as $f)if((int)basename($f)>=8)$p->exec(file_get_contents($f));
$p->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"prueba","published");INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Prueba","","");INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1);INSERT INTO service_category_translations(category_code,locale,label) VALUES("hospedaje","es","Alojamiento")');
foreach(['free','featured','page','page_premium'] as $i=>$plan){
 $id=$i+1;$uuid=sprintf('f00d0000-0000-4000-8000-%012d',$id);
 $p->prepare('INSERT INTO businesses(id,public_id,destination_id,category_code,slug,publication_status,information_status) VALUES(?,?,1,"hospedaje",?,"published","ACTIVO")')->execute([$id,$uuid,'prueba-'.str_replace('_','-',$plan)]);
 $p->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")')->execute([$id,'Prueba '.$plan]);
 $p->prepare('INSERT INTO business_subscriptions(business_id,plan_code,status,starts_at,ends_at) VALUES(?,?,"active",datetime("now","-1 day"),datetime("now","+1 day"))')->execute([$id,$plan]);
 $qr=BusinessQrCode::publishedPage($p,$uuid);
 if(in_array($plan,['free','featured'],true)){if($qr!==null)throw new RuntimeException('A card-only plan exposed a page QR');}
 elseif(!$qr||$qr['url']!=='https://explorandolugares.com/negocios/'.$uuid.'/'||$qr['filename']!=='qr-prueba-'.str_replace('_','-',$plan).'.png')throw new RuntimeException('Published page QR is incorrect');
}
$uuid='f00d0000-0000-4000-8000-000000000004';
foreach(['draft','archived'] as $state){$p->prepare('UPDATE businesses SET publication_status=? WHERE id=4')->execute([$state]);if(BusinessQrCode::publishedPage($p,$uuid)!==null)throw new RuntimeException('Private page QR exposed');}
$p->exec('UPDATE businesses SET publication_status="published" WHERE id=4;UPDATE business_subscriptions SET ends_at=datetime("now","-1 day") WHERE business_id=4');
if(BusinessQrCode::publishedPage($p,$uuid)!==null)throw new RuntimeException('Expired page QR exposed');
$p->exec('UPDATE business_subscriptions SET ends_at=datetime("now","+1 day") WHERE business_id=4;UPDATE destinations SET publication_status="draft"');
if(BusinessQrCode::publishedPage($p,$uuid)!==null||BusinessQrCode::publishedPage($p,'not-an-id')!==null)throw new RuntimeException('Unpublished destination or malformed ID exposed');
echo "OK: QR only for current public page entitlements; drafts, archives, card tiers, expiration and private destinations excluded.\n";
$dashboard=file_get_contents(__DIR__.'/../public/business/dashboard.php');
$qrCss=file_get_contents(__DIR__.'/../public/assets/business-qr.css');
$gridCss=file_get_contents(__DIR__.'/../public/assets/business-workspace-grid.css');
if(substr_count($dashboard,'data-business-qr-url=')!==1||!str_contains($dashboard,'business-icon-action business-qr-trigger')||str_contains($dashboard,'<th scope="col">QR</th>')||!str_contains($dashboard,'BusinessWorkspace'))throw new RuntimeException('QR integration restored the old business list or duplicated its actions.');
if(str_contains($qrCss,'min-width:44px')||str_contains($qrCss,'min-height:44px')||!str_contains($gridCss,'width:32px')||!str_contains($gridCss,'@media(pointer:coarse)'))throw new RuntimeException('QR must reuse the compact grid action and its accessible touch size.');
echo "OK: QR integrated once into the current business grid; compact shared actions and touch targets preserved.\n";
