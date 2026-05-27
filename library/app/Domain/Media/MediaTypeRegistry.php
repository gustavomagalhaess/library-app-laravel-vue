<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Media\Contracts\MediaSubtype;
use App\Domain\Media\Exceptions\MediaException;
use App\Domain\Media\Messages\MediaMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Runtime lookup table for media subtypes.
 *
 * Built once at boot from {@see config('media.types')}, the registry is the
 * single point in the stack that knows which subtype models exist. The
 * unified {@see Services\MediaService} and {@see Repositories\EloquentMediaRepository}
 * both depend on it via constructor injection — they never reference the
 * concrete subtype classes directly.
 *
 * Adding a new media type means:
 *   1. Implement {@see MediaSubtype} on the new model.
 *   2. Add the type → class line in config/media.php.
 * The registry, morph map and route constraints all pick it up automatically.
 */
final class MediaTypeRegistry
{
    /** @var array<string, MediaTypeDefinition> */
    private array $byType = [];

    /** @param array<string, class-string<MediaSubtype>> $map type → model class */
    public function __construct(array $map)
    {
        foreach ($map as $type => $modelClass) {
            $type = Str::lower($type);

            // Sanity-check that the model implements the contract — otherwise
            // every downstream introspection (disk, fields, rules) would die
            // with a cryptic "method not found" deep inside the request.
            if (! is_subclass_of($modelClass, MediaSubtype::class)) {
                throw new \InvalidArgumentException(
                    "Media subtype model [$modelClass] for type '$type' must implement ".MediaSubtype::class,
                );
            }

            /** @var class-string<MediaSubtype&Model> $modelClass */

            // The config key is the canonical alias — make sure the model
            // agrees so the URL segment, the morph map, and any code that
            // reads `$model::getMorphAlias()` can't drift.
            if ($modelClass::getMorphAlias() !== $type) {
                throw new \InvalidArgumentException(
                    "Media subtype [$modelClass] declares getMorphAlias()='{$modelClass::getMorphAlias()}' "
                    ."but is registered under '$type' in config/media.php.",
                );
            }

            $this->byType[$type] = new MediaTypeDefinition(
                type: $type,
                modelClass: $modelClass,
                table: (new $modelClass)->getTable(),
                disk: $modelClass::getDisk(),
                specificFields: $modelClass::getSpecificFields(),
                validationRules: $modelClass::getValidationRules(),
            );
        }
    }

    public function has(string $type): bool
    {
        return isset($this->byType[Str::lower($type)]);
    }

    public function for(string $type): MediaTypeDefinition
    {
        $key = Str::lower($type);

        return $this->byType[$key] ?? throw new MediaException(MediaMessage::UNSUPPORTED_MEDIA_TYPE);
    }

    /**
     * @return string[] List of registered morph aliases.
     */
    public function types(): array
    {
        return array_keys($this->byType);
    }

    /**
     * @return array<string, class-string> Map suitable for {@see \Illuminate\Database\Eloquent\Relations\Relation::morphMap()}.
     */
    public function morphMap(): array
    {
        $map = [];
        foreach ($this->byType as $definition) {
            $map[$definition->type] = $definition->modelClass;
        }

        return $map;
    }
}
