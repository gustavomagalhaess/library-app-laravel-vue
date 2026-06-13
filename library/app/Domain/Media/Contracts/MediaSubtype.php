<?php

declare(strict_types=1);

namespace App\Domain\Media\Contracts;

use App\Domain\Media\MediaTypeRegistry;

/**
 * Contract that every media subtype model (Book, future Movie, …) implements.
 *
 * The aim is to keep all per-type knowledge inside the subtype's own model
 * class — no parallel "registry" or "config map" to keep in sync. The
 * {@see MediaTypeRegistry} introspects models through this
 * interface to expose the metadata to the rest of the stack.
 *
 * Adding a new media type therefore boils down to:
 *   1. A Model class implementing this interface.
 *   2. A migration for its subtype-specific columns.
 *   3. One entry in {@see config/media.php} mapping alias → class.
 */
interface MediaSubtype
{
    /**
     * Short, URL-friendly alias used as both the `media.mediable_type`
     * discriminator and the `{type}` segment in the front-controller routes.
     * Lowercase singular by convention (e.g. 'book', 'movie').
     */
    public static function getMorphAlias(): string;

    /**
     * Name of the {@see config('filesystems.disks')} entry where uploaded
     * files for this subtype are stored (e.g. 'books').
     */
    public static function getDisk(): string;

    /**
     * Subtype-only columns — the ones that live on the subtype's own table
     * rather than on the shared `media` row. Used by the controller to pluck
     * them from the request payload and forward them to the service.
     *
     * @return string[]
     */
    public static function getSpecificFields(): array;

    /**
     * Laravel validation rules for the subtype-specific columns above. The
     * shared columns (title, publication_year, file) are validated by the
     * cross-type FormRequest; these rules cover only what's unique to this
     * subtype.
     *
     * @return array<string, array<int, string|object>>
     */
    public static function getValidationRules(): array;
}
