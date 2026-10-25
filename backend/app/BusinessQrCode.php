<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessRepository.php';

/** Read-only: a QR must never advertise a private or non-entitled page. */
final class BusinessQrCode
{
    public static function publishedPage(PDO $db, string $publicId): ?array
    {
        $page=(new BusinessRepository($db))->findPublicPage($publicId,'es');
        if(!$page)return null;
        // Keep the stable ID: the public renderer resolves the current Premium alias.
        return ['url'=>'https://explorandolugares.com/negocios/'.$page['public_id'].'/',
            'name'=>$page['display_name'],
            'filename'=>'qr-'.preg_replace('/[^a-z0-9-]/','',(string)$page['slug']).'.png'];
    }
}
