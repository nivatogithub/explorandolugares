<?php
declare(strict_types=1);

/** Build a label model without database, HTTP or page-specific exceptions. */
final class PlaceLabelPresentation
{
    public static function labels(array $place, string $locale): array
    {
        $region = trim((string)($place['subregion'] ?? ''));
        if ($region === '') $region = trim((string)($place['department'] ?? ''));
        $labels = $region === '' ? [] : [['code'=>'region', 'text'=>$region]];
        if (!empty($place['is_heritage_town'])) {
            $labels[] = ['code'=>'heritage_town', 'text'=>match ($locale) {
                'en'=>'Heritage Town', 'pt-BR'=>'Povoado Patrimônio', default=>'Pueblo Patrimonio',
            }];
        }
        return $labels;
    }
}
