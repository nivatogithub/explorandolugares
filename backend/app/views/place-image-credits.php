<?php
// Attribution follows licensed media data; it is shared by every place.
$licensedMedia=[];
$collectLicensedMedia=static function(?array $media) use (&$licensedMedia):void {
    if ($media && preg_match('/\bCC BY(?:-SA)?\b/i',(string)($media['license_name']??''))) {
        $licensedMedia[(string)($media['path']??$media['asset_path']??'')]=$media;
    }
};
$collectLicensedMedia($d['hero']??null);
foreach ($d['places'] as $creditPlace) foreach ($creditPlace['media']??[] as $creditMedia) $collectLicensedMedia($creditMedia);
if ($licensedMedia):
    $creditTitle=$locale==='en'?'Image credits and licenses':($locale==='pt-BR'?'Créditos e licenças das imagens':'Créditos y licencias de las imágenes');
    $creditReference=$locale==='en'?'Original reference':($locale==='pt-BR'?'Referência original':'Referencia original');
?>
<details class="destination-image-credits"><summary><?=e($creditTitle)?></summary><ul>
<?php foreach ($licensedMedia as $creditMedia): ?>
<li><?=e($creditMedia['alt']??'')?> · <?=e($creditMedia['creator']??'')?><?php foreach (['source_url'=>$creditReference,'license_url'=>($creditMedia['license_name']??'')] as $creditKey=>$creditLabel):$creditUrl=(string)($creditMedia[$creditKey]??'');if(parse_url($creditUrl,PHP_URL_SCHEME)==='https'&&!preg_match('/[\x00-\x20]/',$creditUrl)):?> · <a href="<?=e($creditUrl)?>" rel="<?=e($creditKey==='license_url'?'license noopener noreferrer':'noopener noreferrer')?>"><?=e($creditLabel)?></a><?php endif;endforeach;?></li>
<?php endforeach; ?>
</ul><p><a href="<?=e($localizedRoot)?>creditos-fotograficos/<?=e('#'.$slug)?>"><?=e($creditTitle)?></a></p></details>
<?php endif; ?>
