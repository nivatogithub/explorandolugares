<?php
declare(strict_types=1);
/** Shared Colombian geography used by the public contact forms. */
final class ContactGeography
{
    public static function data(): array
    {
        $geography=json_decode(file_get_contents(__DIR__.'/content/account-geography.json'),true,64,JSON_THROW_ON_ERROR);
        $options=json_decode(file_get_contents(__DIR__.'/contact-options.json'),true,64,JSON_THROW_ON_ERROR);
        return ['departments'=>$geography['regions']['CO'],'municipalities'=>$geography['municipalities'],'prefixes'=>$options['departmentPrefixes']];
    }
}
