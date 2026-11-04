<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
/** Explicit payment page: all permissions, prices, account and order state are prepared by callers. */
final class BusinessPaymentPresentation
{
    public static function render(array $context,array $data,string $publicRoot):void
    {
        $resourceUrls=[];foreach(['account-portal.css','business-workspace.css','account-files.css','payment-instructions.css','business-subscriptions.js','account-files.js','portal-identity.css'] as $name){
            $file=$publicRoot.'/assets/'.$name;if(!is_file($file))throw new RuntimeException('Missing payment resource.');
            $resourceUrls[$name]='/assets/'.$name.'?v='.substr(hash_file('sha256',$file),0,$name==='portal-identity.css'?12:16);
        }
        $e=[PortalPresentation::class,'escape'];
        $paymentInstructions=$data['instructions'];$paymentInstructionsVisible=true;$onboardingChoice=[];
        $checkoutReference=$data['order']['checkout_reference']??'';
        require __DIR__.'/views/account/business-payment.php';
    }
}
