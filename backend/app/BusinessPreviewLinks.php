<?php
declare(strict_types=1);
/** Keep the real link appearance, but never navigate or contact third parties from a private preview. */
final class BusinessPreviewLinks
{
 public static function attributes(string $url,bool $preview,bool $external=false):string{
  if($preview)return 'aria-disabled="true" tabindex="-1"';
  return 'href="'.htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"'.($external?' target="_blank" rel="noopener noreferrer"':'');
 }
}
