<?php
declare(strict_types=1);

/** Maps known request failures to messages without exposing database details. */
final class ContactErrorMessage
{
    public static function errorMessage(Throwable $error,string $locale):string
    {
        $catalog=json_decode(file_get_contents(__DIR__.'/presentation/contact-errors.json'),true,64,JSON_THROW_ON_ERROR);
        $reason=$error instanceof PDOException?'storage_error':($error instanceof PublicRequestException?$error->reason:null);
        if($reason!==null&&isset($catalog[$reason]))return $catalog[$reason][$locale]??$catalog[$reason]['es'];
        return $error->getMessage();
    }

}
