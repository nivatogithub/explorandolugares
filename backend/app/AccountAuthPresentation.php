<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';

/** Explicit account-dialog presentation; no authentication, HTTP or database access. */
final class AccountAuthPresentation
{
    private const TEXTS=[
        'business_back'=>'← Negocios locales','personal_account'=>'Tu cuenta personal','login_heading'=>'Inicia sesión',
        'email'=>'Correo electrónico','password'=>'Contraseña','login_submit'=>'Entrar a mi cuenta',
        'signup_prompt'=>'¿Aún no tienes cuenta?','signup'=>'Crear cuenta personal','forgot_password'=>'Olvidé mi contraseña',
        'recovery_heading'=>'Recuperar contraseña','recovery_submit'=>'Solicitar enlace de recuperación',
        'contact'=>'Contactar al equipo','login_back'=>'Volver a iniciar sesión','account'=>'Tu cuenta',
        'password_saved'=>'Contraseña actualizada','login'=>'Iniciar sesión','new_password'=>'Nueva contraseña',
        'password_confirm'=>'Confirma la contraseña','password_help'=>'Al menos 12 caracteres, con letras y números.',
        'password_submit'=>'Guardar contraseña',
        'registration_heading'=>'Crea tu cuenta personal','your_name'=>'Tu nombre',
        'create_account'=>'Crear cuenta','existing_account'=>'¿Ya tienes cuenta?',
        'registration_intro'=>'Una cuenta personal para viajar y administrar tus negocios. Verificaremos tu correo antes de activar el acceso.',
        'registration_consent'=>'Acepto el tratamiento de mis datos para crear y gestionar mi cuenta según la',
        'privacy_policy'=>'política de privacidad','residence_heading'=>'¿Dónde vives?',
        'residence_help'=>'Tu residencia como persona. No es la ubicación de tus negocios y no se publica en el directorio.',
        'country'=>'País','email_verified'=>'Correo verificado',
        'verify_email'=>'Verifica tu correo electrónico','verification_resend'=>'Solicitar nuevo enlace',
    ];

    public static function render(string $page,array $context,array $data,string $publicRoot):void
    {
        $title=match($page){'login'=>'Iniciar sesión','recover'=>'Recuperar contraseña','password'=>'Crear o cambiar contraseña','personal-registration'=>'Crear cuenta','verify-email'=>'Verifica tu correo','business-account-gate'=>'Tu cuenta antes del negocio',default=>throw new InvalidArgumentException('Unknown account page.')};
        $locale=strtolower((string)$context['locale']);$column=$locale==='en'?0:1;
        $catalog=json_decode(file_get_contents(__DIR__.'/presentation/accounts.json'),true,64,JSON_THROW_ON_ERROR);$copy=[];
        foreach(self::TEXTS as $key=>$source)$copy[$key]=$locale==='es'?$source:($catalog[$source][$column]??$source);
        $copy['saved_places_benefit']=match($locale){'en'=>'Saving places is available to registered users. Sign in or create your account; we will save this place for you.','pt-br'=>'Salvar lugares é uma função para usuários cadastrados. Entre ou crie sua conta; salvaremos este lugar para você.',default=>'Guardar lugares es una función para usuarios registrados. Inicia sesión o crea tu cuenta; guardaremos este lugar para ti.'};
        $floating=$page!=='business-account-gate';
        $hasFavicon=in_array($page,['login','password'],true);
        $hasAccessStyles=$page!=='personal-registration';
        if($page==='personal-registration')$data['residence']['copy']=['heading'=>$copy['residence_heading'],'help'=>$copy['residence_help'],'country'=>$copy['country']];
        $resourceUrls=[];
        // Keep the existing final identity stylesheet and its cascade during extraction.
        foreach(['registration'=>'account-registration.css','access'=>'account-access.css','identity'=>'portal-identity.css'] as $key=>$name){
            $file=$publicRoot.'/assets/'.$name;
            if(!is_file($file))throw new RuntimeException('Missing account resource.');
            $resourceUrls[$key]='/assets/'.$name.'?v='.substr(hash_file('sha256',$file),0,12);
        }
        $e=[PortalPresentation::class,'escape'];
        require __DIR__.'/views/account/auth-page.php';
    }
}
