<?php
declare(strict_types=1);
/** Called only by the owning disposable-MariaDB CLI test on reserved dev1. */
function order_http_suite(PDO $p,string $schema,string $source,string $folder,array $choices):array
{
    $admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $user='el_order_'.bin2hex(random_bytes(6));$password=bin2hex(random_bytes(24));$account=$admin->quote($user)."@'127.0.0.1'";
    $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));$admin->exec('GRANT ALL ON `'.$schema.'`.* TO '.$account);
    $private=$source.'/private';if(is_dir($private))throw new RuntimeException('HTTP fixture private directory already exists.');mkdir($private,0700);mkdir($private.'/sessions',0700);
    $config=['dsn'=>'mysql:host=127.0.0.1;dbname='.$schema.';charset=utf8mb4','db_user'=>$user,'db_password'=>$password,'app_key'=>bin2hex(random_bytes(32)),'local_test'=>true,'business_accounts_enabled'=>true,'unified_admin_only'=>true,'account_mail'=>['enabled'=>false]];
    file_put_contents($private.'/config.php','<?php return '.var_export($config,true).';');
    $p->exec('INSERT INTO account_roles(account_id,role) VALUES(2,"administrator")');
    $token=bin2hex(random_bytes(32));$gateway='/srv/state/release/public/__local-payment-order-'.bin2hex(random_bytes(8)).'.php';$url='/'.basename($gateway);$checks=[];
    $code='<?php if(getenv("EL_PREPROD_LOCAL")!=="1"||!in_array($_SERVER["REMOTE_ADDR"]??"",["127.0.0.1","::1"],true)||!hash_equals('.var_export($token,true).',$_SERVER["HTTP_X_EL_TEST_TOKEN"]??"")){http_response_code(404);exit;} $routes=["payment"=>"business/payment.php","workspace"=>"business/dashboard.php","admin"=>"admin/subscriptions.php","evidence"=>"admin/payment-evidence.php"]; $route=$routes[$_GET["__route"]??"payment"]??null;if(!$route){http_response_code(404);exit;}unset($_GET["__route"]);ini_set("session.save_path",'.var_export($private.'/sessions',true).');$_SERVER["DOCUMENT_ROOT"]='.var_export($source.'/public',true).';$_SERVER["REQUEST_URI"]="/".$route;$_SERVER["HTTP_X_EL_LOCAL_REGRESSION"]="1";require '.var_export($source.'/public/',true).'.$route;';
    file_put_contents($gateway,$code);chmod($gateway,0644);
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $entry){chown($entry->getPathname(),'www-data');chmod($entry->getPathname(),$entry->isDir()?0700:0600);}chown($private,'www-data');
    $sessions=[];
    $session=static function(int $id,bool $isAdmin=false)use($p,$private,&$sessions):array{
        ini_set('session.save_path',$private.'/sessions');session_name('el_session');session_id(bin2hex(random_bytes(24)));session_start();$_SESSION=['csrf'=>bin2hex(random_bytes(32)),'business_account_id'=>$id,'business_last_seen'=>time()];AccountSessionSecurity::bind($p,$_SESSION,$id);
        if($isAdmin)$_SESSION+=['admin'=>true,'admin_account_id'=>$id,'last_seen'=>time()];$out=[session_id(),$_SESSION['csrf']];session_write_close();$file=$private.'/sessions/sess_'.$out[0];chown($file,'www-data');chmod($file,0600);$sessions[]=$file;return $out;
    };
    require_once $source.'/app/AccountSessionSecurity.php';
    $request=static function(string $session,string $route,array $get=[],?array $post=null,?string $proof=null)use($url,$token):array{
        $headers='Cookie: el_session='.$session."\r\nX-EL-Test-Token: ".$token;$body=null;
        if($post!==null){$body=http_build_query($post);$headers.="\r\nContent-Type: application/x-www-form-urlencoded";}
        if($proof!==null){$boundary='test-'.bin2hex(random_bytes(8));$body='';foreach($post as $k=>$v)$body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$k."\"\r\n\r\n".$v."\r\n";$body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"payment_proof\"; filename=\"proof.pdf\"\r\nContent-Type: application/pdf\r\n\r\n".$proof."\r\n--".$boundary."--\r\n";$headers='Cookie: el_session='.$session."\r\nX-EL-Test-Token: ".$token."\r\nContent-Type: multipart/form-data; boundary=".$boundary;}
        $stream=fopen('http://127.0.0.1'.$url.'?'.http_build_query(['__route'=>$route,...$get]),'r',false,stream_context_create(['http'=>['method'=>$post===null?'GET':'POST','ignore_errors'=>true,'follow_location'=>0,'header'=>$headers,'content'=>$body??'','timeout'=>30]]));if(!$stream)throw new RuntimeException('Local HTTP unavailable.');$html=stream_get_contents($stream);$lines=stream_get_meta_data($stream)['wrapper_data'];fclose($stream);preg_match('/\s(\d{3})\s/',$lines[0],$m);$received=[];foreach($lines as $line){$kv=explode(':',$line,2);if(count($kv)===2)$received[strtolower($kv[0])]=trim($kv[1]);}return [(int)$m[1],$html,$received];
    };
    $check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException('HTTP order test failed: '.$name);$checks[]=$name;};
    try{
        [$sid,$csrf]=$session(1);[$aid,$ac]=$session(2,true);[$status,$html]=$request($sid,'payment');$check($status===200&&str_contains($html,'name="businesses[]"')&&!str_contains($html,'name="payment_proof"'),'selection has no premature upload');
        preg_match('/name="request_key" value="([a-f0-9]{48})"/',$html,$key);
        $post=['csrf'=>$csrf,'action'=>'prepare_order','request_key'=>$key[1],'businesses'=>array_column($choices,'business_id')];foreach($choices as $c){$id=$c['business_id'];$post['plans'][$id]=$c['plan_code'];$post['periods'][$id]=$c['billing_frequency'];$post['experiences'][$id]=$c['experience_id'];}
        [$status,,$headers]=$request($sid,'payment',[],$post);$check($status===303&&preg_match('/order=([a-f0-9]{48})/',$headers['location']??'',$match),'prepare grouped order redirects');$public=$match[1];
        [$status,,$again]=$request($sid,'payment',[],$post);$check($status===303&&$headers['location']===$again['location'],'repeated preparation reuses order');
        $read=new BusinessPaymentOrderSelector($p);$order=$read->order($public,1);$amount=SubscriptionChangeService::money((int)$order['total_cents']);
        [$status,$html]=$request($sid,'payment',['order'=>$public]);$check($status===200&&str_contains($html,'Total de la orden')&&str_contains($html,$amount),'group summary and exact total');
        // Enable simulation only in this disposable schema; never alter actual dev payment settings.
        $p->exec('INSERT INTO payment_instructions(id,enabled,test_mode,holder,breb_key,updated_at) VALUES(1,1,1,"PRUEBA","SIMULACION",CURRENT_TIMESTAMP)');
        [$status,$html]=$request($sid,'payment',['order'=>$public]);$check(str_contains($html,'SIMULACIÓN')&&substr_count($html,'name="payment_proof"')===1,'one upload and simulation notice');
        $evidence=['csrf'=>$csrf,'action'=>'submit_evidence','order'=>$public,'payment_reference'=>'HTTP-GROUP-TEST','transaction_date'=>date('Y-m-d'),'paid_amount'=>$amount,'payment_method'=>'transfer','source_bank'=>'Banco ficticio'];
        [$status]=$request($sid,'payment',['order'=>$public],$evidence,'<svg>not a receipt</svg>');$check($status===400&&$read->evidence((int)$order['id'])===null,'invalid upload leaves order unchanged');
        $proof="%PDF-1.4\n1 0 obj<</Type /Catalog>>endobj\n%%EOF";[$status]=$request($sid,'payment',['order'=>$public],$evidence,$proof);$check($status===303,'real multipart PDF accepted');$receipt=$read->evidence((int)$order['id']);$check($receipt&&file_get_contents($private.'/business-payment-evidence/'.$receipt['storage_name'])===$proof,'one private file byte identical');
        [$status]=$request($sid,'payment',['order'=>$public],$evidence);$check($status===303&&$read->evidence((int)$order['id'])['id']===$receipt['id'],'HTTP submit retry idempotent');
        [$status,$html]=$request($aid,'payment',['order'=>$public]);$check($status===400&&!str_contains($html,$order['checkout_reference']),'other account cannot view order');
        [$status,$html]=$request($aid,'admin');$check($status===200&&str_contains($html,'Orden agrupada')&&str_contains($html,$order['checkout_reference']),'admin sees one grouped receipt and breakdown');
        [$status,$body,$h]=$request($aid,'evidence',['id'=>$receipt['id']]);$check($status===200&&$body===$proof&&str_contains($h['content-type']??'','application/pdf'),'existing private download serves grouped proof');
        [$status]=$request($aid,'admin',[],['csrf'=>$ac,'action'=>'review_payment','evidence_id'=>$receipt['id'],'decision'=>'approved','admin_note'=>'','customer_message'=>'']);$check($status===200,'admin confirms all allocations');
        foreach($read->items((int)$order['id']) as $item)$check($read->subscription((int)$item['subscription_id'])['status']==='paid_pending_publication','each allocation confirmed');
        [$status,$html]=$request($sid,'payment',['order'=>$public]);$check($status===200&&str_contains($html,'Pago confirmado para todos')&&!str_contains($html,'name="payment_proof"'),'confirmed order offers no second payment');
        [$status,$html]=$request($sid,'workspace',['tab'=>'business']);$check($status===200&&str_contains($html,$order['checkout_reference']),'customer history includes grouped reference');
        [$status]=$request($sid,'payment',[],['csrf'=>'invalid','action'=>'prepare_order']);$check($status===303,'CSRF rejected before new order');
        return ['checks'=>count($checks),'cases'=>$checks,'real_http_multipart'=>true,'isolated_mariadb'=>true];
    }finally{
        if(is_file($gateway))unlink($gateway);$admin->exec('DROP USER '.$account);
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $entry){if($entry->isDir())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($private);
    }
}
