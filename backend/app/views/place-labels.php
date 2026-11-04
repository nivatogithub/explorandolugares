<?php
/** @var array $placeLabels Explicit public label model; no data access in this view. */
?>
<?php if (!empty($placeLabels)): ?><div class="place-labels"><?php foreach ($placeLabels as $placeLabel): ?>
<span class="badge" data-place-label="<?=htmlspecialchars($placeLabel['code'],ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($placeLabel['text'],ENT_QUOTES,'UTF-8')?></span>
<?php endforeach; ?></div><?php endif; ?>
