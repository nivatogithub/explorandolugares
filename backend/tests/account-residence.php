<?php
declare(strict_types=1);
require __DIR__.'/../app/AccountResidence.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function input($country,$region,$city='',$name=''){return ['residence_country_code'=>$country,'residence_region_code'=>$region,'residence_city_code'=>$city,'residence_city_name'=>$name];}
$c=AccountResidence::capture(input('CO','05','05368'));check($c['city_name']==='JERICÓ','canonical Jericó');
foreach([input('CO','25','05368'),input('BR','CA','','São Paulo'),input('US','CA'),input('XX','CA','','Test')] as $bad){try{AccountResidence::capture($bad);throw new LogicException('accepted bad input');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}}
check(AccountResidence::capture(input('US','CA','','Los Angeles'))['region_name']==='California','US region');check(AccountResidence::capture(input('BR','SP','','São Paulo'))['region_name']==='São Paulo','BR region');
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->exec('PRAGMA foreign_keys=ON;CREATE TABLE accounts(id INTEGER PRIMARY KEY);INSERT INTO accounts VALUES(1),(2);');$p->exec(file_get_contents(__DIR__.'/../database/migrations/038-account-residence.sqlite.sql'));AccountResidence::save($p,1,$c);check(AccountResidence::get($p,1)['city_code']==='05368','persist');check(!AccountResidence::get($p,2),'no inferred residence');AccountResidence::save($p,1,AccountResidence::capture(input('BR','SP','','São Paulo')));check($p->query('SELECT COUNT(*) FROM account_residences')->fetchColumn()==1,'no duplicates');$p->exec('DELETE FROM accounts WHERE id=1');check(!$p->query('SELECT COUNT(*) FROM account_residences')->fetchColumn(),'cascade');echo "PASS residence validation, cross-region rejection, persistence, isolation and cascade\n";

function db(){return null;}
function esc($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$_POST=[];$residenceValues=[];ob_start();require __DIR__.'/../public/business/residence-fields.php';$html=ob_get_clean();
check(preg_match('/<option value="CO"\s+selected>/',$html)===1,'Colombia initially selected');
check(str_contains($html,'data-region-label>Departamento</span>')&&str_contains($html,'data-city-label>Municipio / ciudad o pueblo</span>'),'Colombian labels without mixed terminology');
check(preg_match_all('/<input[^>]+hidden disabled/',$html)===2,'International text alternatives hidden and disabled for Colombia');
check(str_contains($html,'value="US"')&&str_contains($html,'value="BR"'),'International countries remain available');
check(AccountResidence::capture(['residence_country_code'=>'US','residence_region_name'=>'Florida','residence_city_name'=>'Miami'])['region_name']==='Florida','International free text without catalog code');
check(count(AccountResidence::catalog()['regions']['CO'])===33&&count(AccountResidence::catalog()['municipalities'])===1122,'Colombia catalog includes departments/district and municipalities');
check(str_contains($html,'>Jericó</option>')&&!str_contains($html,'>JERICÓ</option>'),'Municipality labels use title case');
echo "PASS Colombian dropdown defaults, catalog coverage and hidden fallback inputs\n";
