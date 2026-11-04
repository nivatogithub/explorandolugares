<?php
declare(strict_types=1);
echo '<div class="home-attractions" data-home-attractions>';
if($content!==null)echo $content;else readfile($fallback);
echo '</div>';
