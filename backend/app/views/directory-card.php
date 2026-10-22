<?php
$fullBusinessName=$business['display_name'];$features=$business['features']??[];
$hasPage=isset($features['business_page']);$isFeatured=isset($features['highlighted_card']);
$hasLogo=isset($features['business_logo'])&&$business['plan_code']!=='featured';
$pageUrl=$business['demo_page_url']??u('/'.$localePrefix.'negocios/'.$business['public_id'].'/');
$categoryLabel=implode(' · ',$business['category_labels']);$free=$business['plan_code']==='free';
$categoryTab=in_array($business['code'],array_column($d['categories']??[],'code'),true);
?>
<article class="directory-card directory-card-tier-<?=e($business['plan_code'])?><?=$isFeatured?' is-featured':''?>" data-icon-layout="1" data-category="<?=e($business['code'])?>" data-categories="<?=e(implode(' ',$business['category_codes']))?>" id="negocio-<?=e($business['public_id'])?>" data-business-id="<?=e($business['public_id'])?>">
<?php if($business['plan_code']==='page_premium'):?><span class="directory-premium-star" role="img" aria-label="Premium" title="Premium">★</span><?php endif;?>
<div class="directory-card-body"><div class="directory-compact-heading">
<?php if($free):?><span class="directory-free-category-icon" role="img" aria-label="<?=e($categoryLabel)?>" title="<?=e($categoryLabel)?>"><?=directoryIcon($business['code'],true)?></span><?php elseif($hasLogo):?><div class="directory-business-mark"><?php if($business['image_path']):?><img src="<?=e(mediaPath(['path'=>$business['image_path']]))?>" alt="" width="38" height="38" loading="lazy" decoding="async"><?php else:?><span aria-hidden="true"><?=e(businessInitials($business['display_name']))?></span><?php endif;?></div><?php endif;?>
<div class="directory-heading-copy"><h3 class="directory-business-title" title="<?=e($fullBusinessName)?>"><?php if($hasPage):?><a href="<?=e($pageUrl)?>" aria-label="<?=e($fullBusinessName)?>"><?=e($fullBusinessName)?></a><?php else:?><span aria-label="<?=e($fullBusinessName)?>"><?=e($fullBusinessName)?></span><?php endif;?></h3>
<?php if(!$free):?><?php if($categoryTab):?><button class="directory-category-chip" type="button" data-filter-category="<?=e($business['code'])?>"><?=e($categoryLabel)?></button><?php else:?><span class="directory-category-chip"><?=e($categoryLabel)?></span><?php endif;?><?php endif;?></div></div>
<?php if(!$free&&$business['summary']!==''):?><p class="directory-description"><?=e($business['summary'])?></p><?php endif;?>
<?=directoryActions($business,$d['public_contacts'][(int)$business['id']]??[],$locale,$hasPage?$pageUrl:null)?>
</div></article>
