<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
require_once __DIR__.'/AccountProfilePresentation.php';

/** Explicit workspace rendering; authorization, actions and data loading stay with callers. */
final class BusinessWorkspacePresentation
{
    public static function render(array $context,array $data,string $publicRoot):void
    {
        $resources=[
            'portal'=>'account-portal.css','workspace'=>'business-workspace.css',
            'grid'=>'business-workspace-grid.css','qr'=>'business-qr.css','identity'=>'portal-identity.css',
            'workspaceScript'=>'business-workspace.js','countersScript'=>'business-text-counters.js',
            'qrVendor'=>'vendor/qrcodegen-v1.8.0.js','qrScript'=>'business-qr.js',
        ];
        $resourceUrls=[];
        foreach($resources as $key=>$name){
            $file=$publicRoot.'/assets/'.$name;
            if(!is_file($file))throw new RuntimeException('Missing workspace resource.');
            // Preserve the versions emitted by the previous controller and builder.
            $resourceUrls[$key]='/assets/'.$name.'?v='.substr(hash_file('sha256',$file),0,$key==='identity'?12:16);
        }
        $e=[PortalPresentation::class,'escape'];
        require __DIR__.'/views/account/business-workspace.php';
    }

    /** Decorative shapes; the surrounding control supplies its accessible name. */
    public static function icon(string $name):string
    {
    $shape=match($name){
        'edit'=>'<path d="m16 3 5 5-12 12H4v-5Z"/><path d="m14 5 5 5"/>',
        'payment'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
        'card'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c0-3 6-3 6 0m3-6h4m-4 4h4"/>',
        'page'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 8h16M8 12h8m-8 4h5"/>',
        'publish'=>'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'comment'=>'<path d="M21 11a8 8 0 0 1-8 8H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/><path d="M7 8h10M7 12h7"/>',
        'external'=>'<path d="M14 3h7v7m0-7L11 13m-1-8H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/>',
        'info'=>'<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/>',
        default=>throw new InvalidArgumentException('Unknown workspace icon.'),
    };
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$shape.'</svg>';
    }
}
