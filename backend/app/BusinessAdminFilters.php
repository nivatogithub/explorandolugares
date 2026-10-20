<?php
declare(strict_types=1);
final class BusinessAdminFilters
{
    public static function matches(array $row,array $filters,array $duplicates=[]):bool
    {
        foreach(['destination'=>'destination_id','category'=>null,'status'=>'publication_status','verification'=>'information_status','origin'=>'record_origin','plan'=>'plan_code'] as $key=>$column){
            $value=$filters[$key]??'';if($value===''||$value===0)continue;
            if($key==='category'){if(!in_array($value,$row['category_codes']??[$row['category_code']],true))return false;}
            elseif((string)($row[$column]??'')!==(string)$value)return false;
        }
        if(($filters['account']??'')==='yes'&&trim((string)($row['account_email']??''))==='')return false;
        if(($filters['account']??'')==='no'&&trim((string)($row['account_email']??''))!=='')return false;
        $query=trim((string)($filters['query']??''));
        if($query!==''&&mb_stripos($row['display_name'].' '.$row['destination_name'],$query)===false)return false;
        $duplicate=isset($duplicates[(int)$row['id']]);
        return !(($filters['duplicates']??'')==='yes'&&!$duplicate)&&!(($filters['duplicates']??'')==='no'&&$duplicate);
    }
}
