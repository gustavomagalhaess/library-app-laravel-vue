<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Media\Contracts\MediaSubtype;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable snapshot of the metadata one media subtype exposes.
 *
 * Built once per type at boot time from the registered {@see MediaSubtype}
 * model class — the rest of the stack reads these fields instead of calling
 * static methods on the model directly, which keeps the runtime code free of
 * `Book::`/`Movie::` branches.
 *
 * @template-covariant TModel of Model&MediaSubtype
 */
final readonly class MediaTypeDefinition
{
    /**
     * @param  string  $type  Morph alias / URL segment (e.g. 'book')
     * @param  class-string<TModel>  $modelClass  Subtype model FQN
     * @param  string  $table  Subtype's database table name (e.g. 'books')
     * @param  string  $disk  Filesystem disk name (e.g. 'books')
     * @param  string[]  $specificFields  Subtype-only column names
     * @param  array<string, array<int, mixed>>  $validationRules  Laravel rules for the subtype-only columns
     */
    public function __construct(
        public string $type,
        public string $modelClass,
        public string $table,
        public string $disk,
        public array $specificFields,
        public array $validationRules,
    ) {}
}
