<?php
declare(strict_types=1);
echo '<div data-privacy-info>';
foreach($lines as $line)echo '<p>'.PortalPresentation::escape($line).'</p>';
echo '</div>';
