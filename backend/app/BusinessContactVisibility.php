<?php
declare(strict_types=1);

/** Public presentation only. Never deletes or authorises stored private channels. */
final class BusinessContactVisibility
{
    public static function channels(string $plan): array
    {
        return match ($plan) {
            'featured' => ['phone', 'phone_fixed', 'whatsapp'],
            'page' => ['phone', 'phone_fixed', 'whatsapp', 'email', 'instagram'],
            'page_premium' => ['phone', 'phone_fixed', 'whatsapp', 'email', 'instagram', 'website', 'facebook', 'linkedin'],
            default => [],
        };
    }

    public static function filter(string $plan, array $contacts): array
    {
        $allowed = self::channels($plan);
        return array_values(array_filter($contacts, static fn(array $contact): bool =>
            (!array_key_exists('is_public', $contact) || (int)$contact['is_public'] === 1)
            && in_array($contact['channel'] ?? '', $allowed, true)));
    }

    /** Fixed SVG paths only; no contact data is interpolated into SVG. */
    public static function icon(string $channel): string
    {
        $path=match($channel){
          'phone','phone_fixed'=>'<path d="M5 3h4l2 5-3 2a15 15 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2C10 21 3 14 3 5a2 2 0 0 1 2-2Z"/>',
          'whatsapp'=>'<path d="M20.5 11.5a8.4 8.4 0 0 1-12.4 7.4L3.5 20l1.2-4.3A8.4 8.4 0 1 1 20.5 11.5Z"/><path d="M8.8 8.1c.2-.5.5-.5.7-.5h.6c.2 0 .4.1.5.4l.8 1.9c.1.2.1.4 0 .6l-.5.7c-.1.1-.1.3 0 .4.5.9 1.2 1.6 2.1 2.1.1.1.3.1.4 0l.7-.5c.2-.1.4-.1.6 0l1.9.8c.3.1.4.3.4.5v.6c0 .2 0 .5-.5.7-.4.2-1.2.3-2.1 0-1.1-.4-2.4-1.2-3.5-2.3-1.1-1.1-1.9-2.4-2.3-3.5-.3-.9-.2-1.7 0-2.1Z"/>',
          'instagram'=>'<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".6"/>',
          'email'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
          'location'=>'<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2"/>',
          'facebook'=>'<path d="M15 3h-3a4 4 0 0 0-4 4v14m-3-11h11m-4-3v14"/>',
          'linkedin'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 10v7m0-10v.1M11 17v-7m0 3c0-4 6-4 6 0v4"/>',
          default=>'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18m-16-5h14M5 17h14"/>'};
        return '<svg class="business-contact-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$path.'</svg>';
    }

    public static function label(string $channel, string $locale): string
    {
        return match ($channel) {
            'phone', 'phone_fixed' => match ($locale) {'en'=>'Phone', 'pt-BR'=>'Telefone', default=>'Teléfono'},
            'email' => match ($locale) {'en'=>'Email', 'pt-BR'=>'E-mail', default=>'Correo'},
            'website' => match ($locale) {'en'=>'Website', 'pt-BR'=>'Site', default=>'Sitio web'},
            'whatsapp'=>'WhatsApp', 'instagram'=>'Instagram', 'facebook'=>'Facebook', 'linkedin'=>'LinkedIn',
            default => '',
        };
    }
}
