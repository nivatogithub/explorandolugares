<?php
declare(strict_types=1);
require __DIR__.'/../app/LeadGeography.php';
$geo=json_decode(file_get_contents(__DIR__.'/../app/content/account-geography.json'),true);
foreach($geo['municipalities'] as $row){$r=LeadGeography::resolve($row['code'],$row['department_code']);if(!$r||!in_array($r['prefix'],['601','602','604','605','606','607','608'],true))throw new RuntimeException('Municipio nacional no admitido: '.$row['code']);}
if(LeadGeography::resolve('11001','05')!==null||LeadGeography::resolve('inventado')!==null)throw new RuntimeException('No rechaza geografía alterada');
foreach(['11001'=>'601','13001'=>'605','47001'=>'605','05001'=>'604','86001'=>'608'] as $code=>$prefix)if(LeadGeography::resolve((string)$code)['prefix']!==$prefix)throw new RuntimeException('Indicativo incorrecto');
echo 'OK: catálogo nacional completo, departamento coherente, códigos inequívocos e indicativos del servidor.'.PHP_EOL;
