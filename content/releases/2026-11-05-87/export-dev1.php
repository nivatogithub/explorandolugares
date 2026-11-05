<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(80);
$config = require '/srv/state/private/config.php';
if (empty($config['local_test']) || !str_contains($config['dsn'], 'dbname=el_test_destination_')) exit(81);
require '/srv/portal/app/PlaceAdminService.php';
$db = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
$scope = json_decode(file_get_contents(__DIR__.'/scope.json'), true, 64, JSON_THROW_ON_ERROR);
$slugs = array_merge(...array_values(array_column($scope, 'slugs')));
if (count(array_unique($slugs)) !== 87) throw new RuntimeException('Unexpected scope');
$reader = new PlaceAdminService($db);
$pack = ['schema_version'=>2, 'editorial'=>[], 'tables'=>[]];
$destinationIds = $mediaIds = [];
foreach ($slugs as $slug) {
    $query = $db->prepare("SELECT id FROM destinations WHERE slug=? AND publication_status='published'");
    $query->execute([$slug]);
    $id = $query->fetchColumn();
    if (!$id) throw new RuntimeException("Missing published guide: $slug");
    $guide = $reader->find((int)$id);
    unset($guide['media_options'], $guide['internal_research_json'], $guide['labels'], $guide['labels_submitted']);
    $guide['places'] = array_values(array_filter($guide['places'], fn($p) => $p['publication_status'] === 'published'));
    foreach ($guide['destination_media'] as $m) $mediaIds[] = $m['media_asset_id'];
    foreach ($guide['places'] as $p) if ($p['media_asset_id']) $mediaIds[] = $p['media_asset_id'];
    $pack['editorial'][$slug] = $guide;
    $destinationIds[] = (int)$id;
}
function selected(PDO $db, string $table, string $field, array $ids, string $condition=''): array {
    if (!$ids) return [];
    $list = implode(',',array_unique(array_map('intval',$ids)));
    return $db->query("SELECT * FROM `$table` WHERE `$field` IN ($list) $condition ORDER BY 1,2")->fetchAll();
}
$tables = &$pack['tables'];
$tables['businesses'] = selected($db,'businesses','destination_id',$destinationIds,"AND publication_status='published' AND record_origin='public_import' AND claim_status='NO_RECLAMADO'");
$ids = array_column($tables['businesses'],'id');
foreach (['business_translations','business_categories','business_contacts','business_sources','business_media'] as $table)
    $tables[$table] = selected($db,$table,'business_id',$ids,$table==='business_contacts'?'AND is_public=1':'');
foreach ($tables['business_media'] as $m) $mediaIds[]=$m['media_asset_id'];
$tables['media_assets']=selected($db,'media_assets','id',$mediaIds);
$tables['media_asset_translations']=selected($db,'media_asset_translations','media_asset_id',$mediaIds);
file_put_contents(__DIR__.'/public-content.json',json_encode($pack,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo count($slugs).' guides, '.count($ids).' public businesses, '.count($tables['media_assets'])." media\n";
