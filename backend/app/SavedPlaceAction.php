<?php
declare(strict_types=1);
/** Pure presentation data for the bookmark rendered in a place guide. */
final class SavedPlaceAction
{
 public static function render(array $bookmark):void{
  require_once __DIR__.'/PortalPresentation.php';
  $e=[PortalPresentation::class,'escape'];require __DIR__.'/views/saved-place-action.php';
 }
 public static function accessNoticeRequired(array $query):bool{return ($query['beneficio']??'')==='lugares';}
 public static function guidePath(string $slug,string $locale):string{
  return match($locale){'en'=>'/en','pt-BR','pt-br'=>'/pt-br',default=>''}.'/lugares/'.rawurlencode($slug).'/';
 }
 public static function context(string $slug,string $locale,bool $authenticated,bool $saved,string $csrf=''):array{
  $copy=match($locale){
   'en'=>['Save place','Saved — remove from my places','Saving places is available to registered users. Create an account or sign in.'],
   'pt-BR','pt-br'=>['Salvar lugar','Salvo — remover dos meus lugares','Salvar lugares é uma função para usuários cadastrados. Crie sua conta ou entre.'],
   default=>['Guardar lugar','Guardado — quitar de mis lugares','Guardar lugares es una función para usuarios registrados. Crea tu cuenta o inicia sesión.'],
  };
  return ['slug'=>$slug,'locale'=>$locale,'authenticated'=>$authenticated,'saved'=>$saved,'csrf'=>$csrf,'label'=>$saved?$copy[1]:$copy[0],'help'=>$copy[2],'endpoint'=>'/business/saved-place.php','authLabel'=>match($locale){'en'=>'Sign in or create an account','pt-BR','pt-br'=>'Entre ou crie sua conta',default=>'Inicia sesión o crea tu cuenta'},'error'=>false,'errorText'=>match($locale){'en'=>'Unable to save. Please try again.','pt-BR','pt-br'=>'Não foi possível salvar. Tente novamente.',default=>'No se pudo guardar. Inténtalo de nuevo.'}];
 }
}
