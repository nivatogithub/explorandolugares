<?php
declare(strict_types=1);

/** Public label codes are data; translated interface names belong to presentation. */
final class PlaceLabels
{
    public const CODES = ['heritage_town'];

    public static function normalize(mixed $labels): array
    {
        if (!is_array($labels)) throw new RuntimeException('Las etiquetas del lugar no son válidas.');
        foreach ($labels as $code) {
            if (!is_string($code) || !in_array($code, self::CODES, true)) throw new RuntimeException('La etiqueta del lugar no es válida.');
        }
        return array_values(array_unique($labels));
    }
}
