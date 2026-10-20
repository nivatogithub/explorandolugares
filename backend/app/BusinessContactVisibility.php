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
