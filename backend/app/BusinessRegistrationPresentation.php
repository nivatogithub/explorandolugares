<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
require_once __DIR__.'/BusinessPhonePresentation.php';
/** Renders an already-authorized registration model; no database or request access. */
final class BusinessRegistrationPresentation
{
    private const TEXTS=[
        'back_to_businesses'=>'← Negocios locales',
        'existing_account'=>'¿Ya tienes cuenta?',
        'sign_in'=>'Inicia sesión'
    ];
    public static function render(array $context,array $data,string $publicRoot):void
    {
        $locale=$context['locale'];$column=$locale==='en'?0:1;
        $catalog=json_decode(file_get_contents(__DIR__.'/presentation/accounts.json'),true,64,JSON_THROW_ON_ERROR);$copy=[];
        foreach(self::TEXTS as $key=>$source)$copy[$key]=$locale==='es'?$source:($catalog[$source][$column]??$source);
        $resourceUrls=[];
        foreach([
            'subscription-choice.css'=>12,
            'leaflet.css'=>12,
            'account-registration.css'=>12,
            'contact-phone.css'=>12,
            'leaflet.js'=>12,
            'admin-business-location.js'=>12,
            'contact-phone.js'=>12,
            'account-files.js'=>12,
            'operator-addon-registration.js'=>12,
            'business-registration-final-step.css'=>12,
            'business-registration-selects.css'=>12,
            'business-text-counters.js'=>12,
            'business-onboarding-wizard.js'=>12,
            'business-phone.js'=>12,
            'business-editor-panels.css'=>12,
            'business-editor-panels.js'=>12,
            'subscription-examples.css'=>16,
            'subscription-examples.js'=>16,
            'portal-identity.css'=>12
        ] as $name=>$length){
            $file=$publicRoot.'/assets/'.$name;
            if(!is_file($file))throw new RuntimeException('Missing registration resource.');
            $resourceUrls[$name]='/assets/'.$name.'?v='.substr(hash_file('sha256',$file),0,$length);
        }
        $input=$data['input'];$csrf=$data['csrf'];$error=$data['error'];
        $places=$data['places'];$selectedPlaceId=$data['selectedPlaceId'];$categories=$data['categories'];
        $currentAccount=$data['account'];$subscriptionOptions=$data['subscriptionOptions'];
        $selectedPlan=$data['selectedPlan'];$selectedPeriod=$data['selectedPeriod'];$periodLabels=$data['periodLabels'];
        $addonPrices=$data['addonPrices'];$operatorExperiences=$data['operatorExperiences'];
        $selectedOperatorExperienceId=$data['selectedOperatorExperienceId'];$operatorPriceText=$data['operatorPriceText'];
        $registrationPlanRules=$data['planRules'];$registrationDescriptionLimit=$data['descriptionLimit'];
        $draftId=$data['draftId'];$draftRevision=$data['draftRevision'];$resumeStep=$data['resumeStep'];
        $savedDraftUploads=$data['savedDraftUploads'];$phone=$data['phone'];
        require __DIR__.'/views/account/business-registration.php';
    }
}
