<?php
declare(strict_types=1);
/** Private holder identification. A valid check digit never proves registration or authority. */
final class BusinessHolderIdentity
{
    public static function capture(array $input):array
    {
        $kind=trim((string)($input['holder_kind']??''));
        if(!in_array($kind,['legal_entity','natural_rut','natural_document','foreign'],true))throw new RuntimeException('Selecciona cómo se identifica el titular del negocio.');
        $name=trim((string)($input['holder_name']??''));
        if(mb_strlen($name)<2||mb_strlen($name)>180)throw new RuntimeException('Escribe el nombre o razón social del titular, entre 2 y 180 caracteres.');
        $type=trim((string)($input['holder_document_type']??''));
        $number=trim((string)($input['holder_number']??''));
        $foreignKind='';$country='CO';
        if($kind==='foreign'){
            $foreignKind=(string)($input['holder_foreign_kind']??'');$country=(string)($input['holder_country_code']??'');
            require_once __DIR__.'/AccountResidence.php';
            if(!in_array($foreignKind,['person','company'],true)||!isset(AccountResidence::catalog()['countries'][$country]))throw new RuntimeException('Selecciona si el titular extranjero es persona o empresa y su país de identificación.');
            if($foreignKind==='company'&&!in_array($type,['FOREIGN_TAX','NIT'],true))throw new RuntimeException('Una empresa extranjera debe indicar su identificación tributaria.');
        }
        if(in_array($kind,['legal_entity','natural_rut'],true)||($kind==='foreign'&&$type==='NIT')){
            $type='NIT';$number=preg_replace('/[.\s]/u','',$number);
            if(!preg_match('/^([0-9]{5,15})-([0-9])$/D',$number,$parts)||!preg_match('/[1-9]/',$parts[1]))throw new RuntimeException('Escribe el NIT con su dígito de verificación, por ejemplo 800197268-4.');
            $weights=[71,67,59,53,47,43,41,37,29,23,19,17,13,7,3];$digits=str_pad($parts[1],15,'0',STR_PAD_LEFT);$sum=0;
            for($i=0;$i<15;$i++)$sum+=(int)$digits[$i]*$weights[$i];
            $rest=$sum%11;$dv=$rest<2?$rest:11-$rest;
            if((int)$parts[2]!==$dv)throw new RuntimeException('El dígito de verificación del NIT no coincide. Cópialo de tu RUT.');
        }else{
            $allowed=$kind==='foreign'?['CE','PPT','PASSPORT','FOREIGN_TAX']:['CC'];
            if(!in_array($type,$allowed,true))throw new RuntimeException('Selecciona el tipo de documento del titular.');
            $number=mb_strtoupper($number);
            if(!preg_match('/^[A-Z0-9][A-Z0-9 .-]{3,29}$/D',$number)||!preg_match('/[A-Z1-9]/',$number))throw new RuntimeException('Revisa el documento: entre 4 y 30 caracteres, con letras o números según su tipo.');
            if(in_array($type,['CC','CE','PPT'],true)&&!preg_match('/^[0-9]{4,15}$/D',$number))throw new RuntimeException('Este tipo de documento requiere entre 4 y 15 dígitos.');
        }
        return ['kind'=>$kind,'foreign_kind'=>$foreignKind,'country_code'=>$country,'name'=>$name,'document_type'=>$type,'number'=>$number,'format_valid'=>true,'officially_verified'=>false,'ownership_verified'=>false];
    }
}
