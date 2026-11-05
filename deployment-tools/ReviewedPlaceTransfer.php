<?php
declare(strict_types=1);
// The launcher loads the existing application transfer before this helper.

/** Explicit owner-approved reviewed place transfer. Never executed by public requests. */
final class ReviewedPlaceTransfer extends ProductionContentTransfer
{
    public function __construct(private PDO $connection, private array $expected) { parent::__construct($connection); }

    public function validate(array $pack): array
    {
        if (($pack['schema_version']??null)!==1 || array_keys($pack['editorial']??[])!==[$this->expected['slug']]
            || ($pack['approval']['original_commit']??'')!==$this->expected['source_commit']
            || ($pack['approval']['approved_by']??'')!=='project_owner'
            || count($pack['editorial'][$this->expected['slug']]['places']??[])!==$this->expected['attractions']
            || count($pack['tables']['businesses']??[])!==$this->expected['businesses']
            || count($pack['tables']['media_assets']??[])!==$this->expected['images']) {
            throw new RuntimeException('Unexpected approved reviewed place scope.');
        }
        $allowed=['businesses','business_translations','business_categories','business_sources','business_contacts','business_media','media_assets','media_asset_translations'];
        if (array_diff(array_keys($pack['tables']),$allowed)) throw new RuntimeException('reviewed place cannot import accounts, payments, settings or measurements.');
        $seen=[];
        foreach ($pack['tables']['businesses'] as $row) {
            if (($row['record_origin']??'')!=='public_import' || ($row['publication_status']??'')!=='published'
                || isset($seen[$row['public_id']]) || !preg_match('/^[a-f0-9-]{36}$/D',$row['public_id'])) {
                throw new RuntimeException('Unexpected reviewed place business identity.');
            }
            $seen[$row['public_id']]=true;
        }
        return ['places'=>[$this->expected['slug']],'businesses'=>$this->expected['businesses'],'media'=>$this->expected['images'],'accounts_imported'=>0,'payments_imported'=>0];
    }

    public function apply(array $pack,string $publicRoot):array
    {
        $this->validate($pack);
        foreach ($pack['tables']['media_assets'] as $asset) {
            $path=$asset['asset_path'];$provenance=json_decode($asset['provenance_json'],true,512,JSON_THROW_ON_ERROR);
            if (!preg_match('~^assets/media/[a-f0-9]{2}/[a-f0-9]{64}\.webp$~D',$path)
                || empty($provenance['approved_by_owner']) || !is_file($publicRoot.'/'.$path)
                || !hash_equals($provenance['sha256'],hash_file('sha256',$publicRoot.'/'.$path))) {
                throw new RuntimeException('Approved reviewed place image is missing or changed.');
            }
        }
        // Never overwrite an unrelated or claimed business, even when names match.
        foreach ($pack['tables']['businesses'] as $row) {
            $names=array_values(array_filter($pack['tables']['business_translations'],static fn($t)=>(int)$t['business_id']===(int)$row['id']&&$t['locale']==='es'));
            $q=$this->connection->prepare('SELECT b.public_id FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? AND LOWER(TRIM(t.display_name))=LOWER(TRIM(?))');
            $q->execute([$this->expected['slug'],$names[0]['display_name']]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $existing) {
                if ($existing!==$row['public_id']) throw new RuntimeException('Existing reviewed place business requires individual reconciliation; no record overwritten.');
            }
        }
        return parent::apply($pack,$publicRoot);
    }
}
