<?php
declare(strict_types=1);

/** Publication decisions over normalized data, independent of HTTP and persistence. */
final class PlacePublicationPolicy
{
    public static function assertInitialState(int $id, string $status): void
    {
        if ($id === 0 && $status !== 'draft') {
            throw new RuntimeException('Los lugares nuevos siempre se crean como borrador. Guárdalo primero, revísalo y luego podrás publicarlo.');
        }
    }

    public static function assertEditorialCopy(array $translation, string $status): void
    {
        if ($status !== 'published') return;
        $text=implode(' ',array_map(static fn($key)=>$translation[$key],['eyebrow','headline','intro','meta_description']));
        if (preg_match('/borrador\s+local|local\s+draft|rascunho\s+local|imágenes\s+definitivas\s+pendientes|final\s+images\s+pending|imagens\s+finais\s+pendentes/iu',$text)) {
            throw new RuntimeException('Retira los avisos internos de borrador antes de publicar el lugar.');
        }
    }

    /** @param list<string> $locales */
    public static function assertAttractionTranslations(array $translations, string $status, array $locales): void
    {
        if ($status !== 'published') return;
        foreach ($locales as $locale) {
            $translation=$translations[$locale];
            if ($translation['name'] === '' || $translation['description'] === '') {
                throw new RuntimeException("Completa la traducción del atractivo publicado en {$locale}.");
            }
            if ($translation['external_url'] !== '' && $translation['external_label'] === '') {
                throw new RuntimeException("Completa el texto del enlace externo en {$locale}.");
            }
        }
    }

    /** Inputs have already passed format/length and geolocation validation. */
    public static function assertReadyToPublish(array $translations, array $history, array $reasons, array $attractions, array $locales): void
    {
        foreach ($locales as $locale) {
            $translation=$translations[$locale];
            if ($translation['display_name'] === '' || $translation['headline'] === '' || $translation['intro'] === '' || !$history[$locale] || !$reasons[$locale]) {
                throw new RuntimeException("Para publicar completa título, introducción, historia y motivos en los tres idiomas; falta contenido en {$locale}.");
            }
        }
        if (!array_filter($attractions,static fn($attraction)=>$attraction['status']==='published')) {
            throw new RuntimeException('Publicar requiere al menos un atractivo con estado «Publicado».');
        }
    }
}
