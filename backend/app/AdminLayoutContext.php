<?php
declare(strict_types=1);

/** Presentation data only; authorization is performed before this is prepared. */
final class AdminLayoutContext
{
    /** @return array{active_item:string,account:array{id:int,display_name:string,csrf:string,logout_url:string}} */
    public static function prepare(string $requestUri,array $query,int $accountId,string $displayName,string $csrf):array
    {
        return ['active_item'=>self::activeItem($requestUri,$query),'account'=>[
            'id'=>$accountId,'display_name'=>$displayName,'csrf'=>$csrf,
            'logout_url'=>$accountId?'/negocios/salir/':'/admin/index.php',
        ]];
    }

    public static function activeItem(string $requestUri,array $query):string
    {
        $adminPath = basename((string)(parse_url($requestUri, PHP_URL_PATH) ?: 'index.php'));
        $adminTab = (string)($query['tab'] ?? '');
        return match (true) {
            $adminPath === 'index.php' && $adminTab === 'sistema' => 'system',
            $adminPath === 'index.php' => 'requests',
            $adminPath === 'email-templates.php' => 'email-workspace',
            $adminPath === 'request-response.php' => 'requests',
            $adminPath === 'request-details.php' => ($query['type']??$query['tipo']??'')==='account'?'account-applications':'requests',
            $adminPath === 'business-support.php' || $adminPath === 'business-editorial.php' || $adminPath === 'editorial.php' || $adminPath === 'editorial-package.php' => 'businesses',
            $adminPath === 'businesses.php' => 'businesses',
            $adminPath === 'transfer-businesses.php' => 'businesses',
            $adminPath === 'import-businesses.php' => 'businesses',
            $adminPath === 'business-contacts.php' => 'campaigns',
            $adminPath === 'update-businesses.php' => 'businesses',
            $adminPath === 'campaigns.php' && $adminTab === 'conexion' => 'brevo-connection',
            $adminPath === 'campaigns.php' => 'campaigns',
            $adminPath === 'claims.php' => 'account-applications',
            $adminPath === 'account-applications.php' || ($adminPath === 'request-details.php' && ($query['tipo']??'')==='account') => 'account-applications',
            $adminPath === 'accounts.php' => 'accounts',
            $adminPath === 'subscriptions.php' || $adminPath === 'coupons.php' => 'subscriptions',
            $adminPath === 'places.php' || str_starts_with($adminPath, 'paquete-destino') => 'places',
            $adminPath === 'reports.php' => 'reports',
            $adminPath === 'public-requests.php' => 'public-requests',
            $adminPath === 'payment-settings.php' => 'payment-settings',
            $adminPath === 'settings.php' => 'configuration',
            $adminPath === 'database.php' => 'database',
            $adminPath === 'investors.php' => 'investor-intelligence',
            $adminPath === 'costs.php' => 'costs',
            $adminPath === 'cancellations.php' => 'cancellations',
            $adminPath === 'incidents.php' => 'incidents',
            default => '',
        };
    }
}
