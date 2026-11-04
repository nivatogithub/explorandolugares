<?php
declare(strict_types=1);

/**
 * Read contract for the place detail and directory use cases.
 *
 * Implementations preserve stable identities, result ordering and publication
 * scopes. Preview includes drafts but does not authorize access: the caller must
 * authorize it first. Results are the current internal projection, not an HTTP
 * API contract; legacy projection keys remain until a separate versioned change.
 * Private research and media provenance must never be returned.
 */
interface PlaceReader
{
    /** A localized published place, or null; preview also admits drafts. */
    public function findPublished(string $slug,string $locale,bool $preview=false):?array;

    /** @return list<string> Locales with a nonblank display name, ordered by locale. */
    public function availableLocales(int $placeId):array;

    /** @return list<string> Nonblank history paragraphs in editorial order. */
    public function history(int $placeId,string $locale):array;

    /** @return list<string> Reasons in editorial order. */
    public function reasons(int $placeId,string $locale):array;

    /** Localized media with its required public attribution, or null. */
    public function media(int $placeId,string $locale,string $role):?array;

    /** @return list<array> Sources ordered by section and editorial position. */
    public function sources(int $placeId,string $locale):array;

    /**
     * One row per attraction/media relation; includes attractions without media.
     * Published only by default; preview includes nonarchived attractions.
     * Media relations retain their order for grouping by stable attraction ID.
     * @return list<array>
     */
    public function attractions(int $placeId,string $locale,bool $preview=false):array;

    /** @return list<array> Published directory rows, ordered by localized name/media. */
    public function listPublished(string $locale):array;
}
