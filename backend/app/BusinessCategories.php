<?php
declare(strict_types=1);

/** Primary category remains canonical; extra categories never create another business. */
final class BusinessCategories
{
    public static function ready(PDO $pdo): bool
    {
        try{$pdo->query('SELECT business_id,category_code FROM business_categories LIMIT 0');return true;}
        catch(PDOException $e){if(in_array($e->errorInfo[0]??'', ['42S02'],true)||str_contains($e->getMessage(),'no such table:'))return false;throw $e;}
    }

    public static function validate(PDO $pdo,mixed $codes,string $primary):array
    {
        if(!is_array($codes)||!array_is_list($codes)||count($codes)>50)throw new RuntimeException('Las categorías deben ser una lista del catálogo.');
        $result=[$primary];$q=$pdo->prepare('SELECT 1 FROM service_categories WHERE code=? AND is_active=1');
        foreach($codes as $code){if(!is_string($code))throw new RuntimeException('Categoría no válida.');$q->execute([$code]);if(!$q->fetchColumn())throw new RuntimeException('La categoría no existe o está inactiva: '.$code);$result[]=$code;}
        $result=array_values(array_unique($result));sort($result);return $result;
    }

    public static function replace(PDO $pdo,int $id,string $primary,array $codes):void
    {
        if(!self::ready($pdo)){if(array_diff($codes,[$primary]))throw new RuntimeException('Aplica la migración 030 para guardar categorías adicionales.');return;}
        $pdo->prepare('DELETE FROM business_categories WHERE business_id=?')->execute([$id]);
        $q=$pdo->prepare('INSERT INTO business_categories(business_id,category_code) VALUES(?,?)');
        foreach(array_unique($codes) as $code)if($code!==$primary)$q->execute([$id,$code]);
    }

    public static function enrich(PDO $pdo,array $rows,string $locale='es',string $primaryKey='category_code',string $labelKey='category_label'):array
    {
        if(!$rows)return [];
        foreach($rows as &$row){$row['category_codes']=[$row[$primaryKey]];$row['category_labels']=[$row[$labelKey]??$row[$primaryKey]];}unset($row);
        if(!self::ready($pdo))return $rows;
        $ids=array_map('intval',array_column($rows,'id'));
        $q=$pdo->prepare('SELECT bc.business_id,c.code,COALESCE(t.label,c.code) AS label FROM business_categories bc JOIN service_categories c ON c.code=bc.category_code AND c.is_active=1 LEFT JOIN service_category_translations t ON t.category_code=c.code AND LOWER(t.locale)=LOWER(?) WHERE bc.business_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY c.display_order,c.code');
        $q->execute([$locale,...$ids]);$extras=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $item)$extras[(int)$item['business_id']][]=$item;
        foreach($rows as &$row)foreach($extras[(int)$row['id']]??[] as $item)if(!in_array($item['code'],$row['category_codes'],true)){$row['category_codes'][]=$item['code'];$row['category_labels'][]=$item['label'];}unset($row);
        return $rows;
    }
}
