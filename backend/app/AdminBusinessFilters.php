<?php
declare(strict_types=1);
/** Shared read-only predicate for the imported list, counts and existing bulk selection. */
final class AdminBusinessFilters
{
    public static function imported(array $row,array $filters,array $duplicates):bool
    {
        if($row['record_origin']!=='public_import')return false;
        if(!empty($filters['destination'])&&(int)$row['destination_id']!==(int)$filters['destination'])return false;
        if(($filters['category']??'')!==''&&!in_array($filters['category'],$row['category_codes']??[$row['category_code']],true))return false;
        if(($filters['query']??'')!==''&&mb_stripos($row['display_name'].' '.$row['destination_name'],$filters['query'])===false)return false;
        if(($filters['status']??'')!==''&&$row['publication_status']!==$filters['status'])return false;
        if(($filters['verification']??'')!==''&&$row['information_status']!==$filters['verification'])return false;
        $duplicate=isset($duplicates[(int)$row['id']]);
        return !(($filters['duplicates']??'')==='yes'&&!$duplicate)&&!(($filters['duplicates']??'')==='no'&&$duplicate);
    }
}
