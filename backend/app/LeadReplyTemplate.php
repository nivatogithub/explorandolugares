<?php
declare(strict_types=1);
final class LeadReplyTemplate
{
    public const VERSION='lead-reply-v1';
    public static function render(string $message,array $offers):array
    {
        $escape=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $prices=[];foreach($offers as $offer)$prices[]=$offer['name'].': $'.number_format((float)$offer['amount'],0,',','.').' COP al mes';
        $text=$message."\n\nSuscripciones vigentes\n".implode("\n",$prices)."\nGratis: nombre y categoría, sin canales públicos de contacto.\nConsulta beneficios, condiciones y tarifas: https://explorandolugares.com/negocios-locales/suscripciones/\nCrea tu cuenta o inicia sesión: https://explorandolugares.com/negocios/acceso/\nDesde tu cuenta puedes buscar y asociar tu negocio o agregarlo si no existe.\nAyuda: https://explorandolugares.com/contacto.php\nEsta es una respuesta individual a tu solicitud; no te incorpora a campañas.";
        $html='<div lang="es" style="font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:24px;background:#fffaf2;color:#172b42"><img src="https://explorandolugares.com/assets/brand/explorando-lugares-logo-v2-transparent.png" width="100" alt="Explorando Lugares"><h1 style="font-size:24px">Respuesta a tu solicitud</h1><p>'.nl2br($escape($message)).'</p><h2 style="font-size:20px">Suscripciones vigentes</h2><ul>';
        foreach($prices as $price)$html.='<li>'.$escape($price).'</li>';
        $html.='</ul><p>Gratis: nombre y categoría, sin canales públicos de contacto.</p><p><a style="display:inline-block;padding:12px 18px;border-radius:20px;background:#d84e27;color:white" href="https://explorandolugares.com/negocios-locales/suscripciones/">Comparar suscripciones y condiciones</a></p><p><a href="https://explorandolugares.com/negocios/acceso/">Crear mi cuenta o iniciar sesión</a></p><p>Desde tu cuenta puedes buscar y asociar tu negocio o agregarlo si no existe.</p><p><a href="https://explorandolugares.com/contacto.php">Contacto y ayuda</a></p><p style="font-size:13px">Respuesta individual a tu solicitud. No te incorpora a campañas.</p></div>';
        return ['html'=>$html,'text'=>$text];
    }
}
