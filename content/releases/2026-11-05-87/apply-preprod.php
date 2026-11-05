<?php
declare(strict_types=1);
// Private CLI release entry point. The container exposes only the preprod database.
if (PHP_SAPI !== 'cli') exit(80);
$c = require '/srv/state/private/config.php';
if (empty($c['local_test']) || !str_contains($c['dsn'],'dbname=explorando_preprod')) exit(81);
require_once '/srv/portal/app/ProductionContentTransfer.php';
final class NovemberPlaceRelease extends ProductionContentTransfer {
    public function validate(array $pack): array {
        $scope=json_decode(file_get_contents(__DIR__.'/scope.json'),true,64,JSON_THROW_ON_ERROR);
        $expected=array_merge(...array_values(array_column($scope,'slugs')));
        $actual=array_keys($pack['editorial']);sort($expected);sort($actual);
        if (count($expected)!==87 || $actual!==$expected || $pack['schema_version']!==2) throw new RuntimeException('Scope mismatch');
        foreach ($pack['editorial'] as $slug=>$place) {
            if ($place['slug']!==$slug || $place['publication_status']!=='published') throw new RuntimeException('Unexpected guide');
        }
        foreach ($pack['tables']['businesses'] as $b) {
            if ($b['record_origin']!=='public_import'||$b['claim_status']!=='NO_RECLAMADO'||$b['publication_status']!=='published') throw new RuntimeException('Unexpected business');
        }
        return ['places'=>$expected,'businesses'=>count($pack['tables']['businesses']),'media'=>count($pack['tables']['media_assets'])];
    }
}
$mode=$argv[1]??'';
if (!in_array($mode,['rehearse','apply'],true)) exit(82);
$manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,64,JSON_THROW_ON_ERROR);
$file=__DIR__.'/public-content.json';
if (!hash_equals($manifest['sha256'],hash_file('sha256',$file))) throw new RuntimeException('Content hash mismatch');
foreach ($manifest['media'] as $m) {
    if ($m['path']!=='assets/destinos/lote-87/'.$m['sha256'].'.webp' || !hash_equals($m['sha256'],hash_file('sha256','/srv/portal/public/'.$m['path']))) throw new RuntimeException('Media mismatch');
}
$pack=json_decode(file_get_contents($file),true,128,JSON_THROW_ON_ERROR);
$db=$mode==='apply'?new PDO($c['dsn'],$c['db_user'],$c['db_password']):new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=el_rehearse_nov87;charset=utf8mb4','root','');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$transfer=new NovemberPlaceRelease($db);
$result=$transfer->apply($pack,'/srv/portal/public');
$db->exec(file_get_contents(__DIR__.'/heritage-colombia-20261104.sql'));
echo json_encode(['mode'=>$mode,'places'=>count($result['places']),'businesses'=>$result['businesses_processed'],'media'=>$result['media']],JSON_THROW_ON_ERROR)."\n";
