<?php
declare(strict_types=1);
/** Existence signals only: never assigns an owner or approves publication. */
final class BusinessExistenceVerification
{
    public const SOURCE='https://www.datos.gov.co/Comercio-Industria-y-Turismo/Registro-Nacional-de-Turismo-RNT/thwd-ivmp';
    public static function capture(array $input):array
    {
        $rnt=trim((string)($input['business_rnt']??''));$nit=trim((string)($input['business_nit']??''));
        // People frequently paste the label and visual separators from their
        // certificate. Store the identifier as digits, without rejecting an
        // otherwise valid application because of its presentation.
        $rnt=preg_replace('/^RNT\b[^0-9]*/iu','',$rnt)??$rnt;
        $rnt=preg_replace('/[.\s-]+/u','',$rnt)??$rnt;
        if(strlen($rnt)>12||($rnt!==''&&!ctype_digit($rnt)))throw new RuntimeException('Revisa el RNT indicado en el documento del negocio.');
        if(strlen($nit)>25||($nit!==''&&!preg_match('/^[0-9. ]+(?:-[0-9])?$/D',$nit)))throw new RuntimeException('Revisa el NIT del negocio; puedes incluir su dígito después de un guion.');
        return ['rnt'=>$rnt,'nit'=>$nit];
    }
    private static function name(string $value):string
    {
        $value=mb_strtolower($value);$value=strtr($value,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);return trim(preg_replace('/[^a-z0-9]+/',' ',$value));
    }
    public static function assessNew(string $name,string $destination,array $ids,?callable $fetch=null):array
    {
        $result=['version'=>1,'checked_at'=>gmdate('c'),'existence'=>'needs_evidence','review'=>'attention','basis'=>'self_reported','reasons'=>[],'source_url'=>self::SOURCE,'ownership_verified'=>false];
        $rnt=$ids['rnt']??'';$nit=preg_replace('/[. ]/','',explode('-',$ids['nit']??'')[0]);
        if($rnt===''&&$nit===''){$result['reasons'][]='Sin RNT o NIT para consultar esta fuente; no significa que el negocio no exista.';return $result;}
        $select='codigo_rnt,nit,razon_social_establecimiento,municipio,estado_rnt,ano,periodo';
        $url='https://www.datos.gov.co/resource/thwd-ivmp.json?'.http_build_query(['$select'=>$select,'$limit'=>51,$rnt!==''?'codigo_rnt':'nit'=>$rnt!==''?$rnt:$nit]);
        try{$rows=$fetch?$fetch($url):self::fetch($url);if(!is_array($rows)||count($rows)>=51||array_filter($rows,static fn($row)=>!is_array($row)))throw new RuntimeException('Respuesta incompleta');}
        catch(Throwable){$result['existence']='source_unavailable';$result['reasons'][]='No se pudo completar la consulta oficial. Conservar solicitud y reintentar; no declarar inexistencia.';return $result;}
        if(!$rows){$result['reasons'][]='No hay coincidencia en este conjunto RNT; no cubre todos los comercios ni demuestra inexistencia.';return $result;}
        $months=['ENERO'=>1,'FEBRERO'=>2,'MARZO'=>3,'ABRIL'=>4,'MAYO'=>5,'JUNIO'=>6,'JULIO'=>7,'AGOSTO'=>8,'SEPTIEMBRE'=>9,'OCTUBRE'=>10,'NOVIEMBRE'=>11,'DICIEMBRE'=>12];
        $period=static fn($row)=>(int)($row['ano']??0)*100+($months[mb_strtoupper($row['periodo']??'')]??0);
        $latest=max(array_map($period,$rows));$rows=array_values(array_filter($rows,static fn($row)=>$period($row)===$latest));
        $matches=array_values(array_filter($rows,static fn($row)=>self::name((string)($row['razon_social_establecimiento']??''))===self::name($name)));
        if(count($matches)!==1){$result['existence']='name_needs_review';$result['reasons'][]='El registro consultado no identifica de forma inequívoca el nombre indicado. Puede ser otra razón social o sede.';return $result;}
        $row=$matches[0];
        if(($rnt!==''&&ltrim((string)($row['codigo_rnt']??''),'0')!==ltrim($rnt,'0'))||($nit!==''&&(string)($row['nit']??'')!==$nit)){$result['existence']='identifier_conflict';$result['review']='priority';$result['reasons'][]='El NIT indicado no coincide con el del RNT encontrado.';return $result;}
        $result['existence']='official_snapshot_match';$result['basis']='rnt_open_data';
        $result['snapshot']=['rnt'=>(string)$row['codigo_rnt'],'name'=>(string)$row['razon_social_establecimiento'],'municipality'=>(string)($row['municipio']??''),'status'=>(string)($row['estado_rnt']??''),'year'=>(string)($row['ano']??''),'period'=>(string)($row['periodo']??'')];
        $result['review']='standard';$result['reasons'][]='Coincidencia exacta de nombre y registro en una publicación oficial histórica; no certifica vigencia actual ni representación.';
        if(self::name((string)($row['municipio']??''))!==self::name($destination)){$result['review']='attention';$result['reasons'][]='Contrastar ubicación: el lugar del portal puede ser distinto del municipio del registro.';}
        if(mb_strtoupper((string)($row['estado_rnt']??''))!=='ACTIVO'){$result['review']='priority';$result['reasons'][]='El estado de la fuente no figura ACTIVO en ese período.';}
        if($latest<(int)gmdate('Ym')){$result['review']=$result['review']==='priority'?'priority':'attention';$result['reasons'][]='El período publicado es anterior al mes actual; revisar vigencia antes de publicar.';}
        return $result;
    }
    public static function assessExisting(PDO $pdo,int $id):array
    {
        $q=$pdo->prepare('SELECT record_origin,information_status,operation_status FROM businesses WHERE id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        $source=$pdo->prepare('SELECT COUNT(*) FROM business_sources WHERE business_id=?');$source->execute([$id]);
        return ['version'=>1,'checked_at'=>gmdate('c'),'existence'=>'known_catalog','basis'=>($row['record_origin']??'')==='public_import'?'imported_catalog':'existing_catalog','review'=>($row['operation_status']??'')==='CERRADO_DEFINITIVO'?'priority':'standard','source_count'=>(int)$source->fetchColumn(),'information_status'=>$row['information_status']??'','ownership_verified'=>false,'reasons'=>['El negocio ya está en nuestro catálogo; reutilizar su procedencia, sin volver a exigir prueba de existencia. La relación de la persona con el negocio se evalúa por separado.']];
    }
    private static function fetch(string $url):array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('cURL no disponible');$body='';$curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($curl,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>262144)return 0;$body.=$chunk;return strlen($chunk);}]);
        try{if(curl_exec($curl)===false||curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Fuente no disponible');$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(!is_array($data)||!array_is_list($data))throw new RuntimeException('Formato de fuente inválido');return $data;}finally{curl_close($curl);}
    }
}
