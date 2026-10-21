<?php
declare(strict_types=1);

/** Primary category remains canonical; extra categories never create another business. */
final class BusinessCategories
{
    /** Same pictograms as business registration, with visible category labels. */
    public static function icon(string $code): string
    {
        $path=match($code){
          'hospedaje'=>'M3 19V7h18v12M3 14h18M7 10h3m4 0h3',
          'alimentacion'=>'M5 3v7m3-7v7m-5-4h7M6 10v11M17 3v18m0-18c5 3 5 8 0 8',
          'comercio'=>'M4 8h16v13H4V8m3 0V6a5 5 0 0 1 10 0v2',
          'experiencias'=>'m2 20 7-14 5 9 3-5 5 10H2m4-8 3 2 3-2',
          'transporte'=>'M4 16V7h16v9H4m3 0v4m10-4v4M4 12h16',
          'cultura'=>'m3 9 9-6 9 6H3m2 2v8m7-8v8m7-8v8M3 21h18',
          'salud'=>'M9 3h6v6h6v6h-6v6H9v-6H3V9h6V3',
          'licores'=>'M8 3h8v8a4 4 0 0 1-8 0V3m4 12v6m-4 0h8',
          'vida-nocturna'=>'M16 3a9 9 0 1 0 5 14A8 8 0 0 1 16 3',
          default=>'M4 4h6v6H4V4m10 0h6v6h-6V4M4 14h6v6H4v-6m10 0h6v6h-6v-6'};
        return '<svg class="business-service-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="'.$path.'"/></svg>';
    }

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
