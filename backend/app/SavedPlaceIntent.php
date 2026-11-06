<?php
declare(strict_types=1);
require_once __DIR__.'/SavedPlaces.php';
require_once __DIR__.'/SavedPlaceAction.php';
/** Explicit bookmark click survives registration; completion runs only after a CSRF-protected login POST. */
final class SavedPlaceIntent
{
 public static function remember(array &$session,string $slug,string $locale):void{
  if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new InvalidArgumentException('Invalid place.');
  $session['saved_place_intent']=['slug'=>$slug,'locale'=>in_array($locale,['es','en','pt-BR'],true)?$locale:'es','created'=>time()];
 }
 public static function complete(PDO $pdo,array &$session,int $accountId):?string{
  $intent=$session['saved_place_intent']??null;unset($session['saved_place_intent']);
  if(!is_array($intent)||$accountId<1||time()-(int)($intent['created']??0)>3600)return null;
  $path=SavedPlaceAction::guidePath((string)$intent['slug'],(string)$intent['locale']);
  (new SavedPlaces($pdo))->change($accountId,(string)$intent['slug'],true);
  return $path;
 }
}
