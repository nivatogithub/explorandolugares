<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
/** Subscription entry presentation. Authorization and selection stay with the controller. */
final class BusinessSelectionPresentation
{
    public static function render(array $context,array $data,string $publicRoot):void
    {
        $resourceUrls=[];
        foreach(['account-registration.css','subscription-simulator.css','portal-identity.css'] as $name){
            $file=$publicRoot.'/assets/'.$name;
            if(!is_file($file))throw new RuntimeException('Missing business selection resource.');
            $resourceUrls[$name]='/assets/'.$name.'?v='.substr(hash_file('sha256',$file),0,12);
        }
        $e=[PortalPresentation::class,'escape'];
        require __DIR__.'/views/account/business-selection.php';
    }
}
