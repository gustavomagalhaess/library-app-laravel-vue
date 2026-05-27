<?php

declare(strict_types=1);

namespace App\Domain\Book\Models;

use App\Domain\Media\Contracts\MediaSubtype;
use App\Domain\Media\Models\Media;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Aggregate root for the Book bounded context.
 *
 * Book is the morph subtype of {@see Media}. The shared columns
 * (title, publication_year, file_path) and the authors relationship live
 * on `media`; Book only owns the book-specific columns (`uuid`, `pages`).
 * Callers reach the shared metadata through the relation —
 * `$book->media->title`, `$book->media->authors`, etc. — so future media
 * subtypes (Movie, Music) don't have to redeclare those accessors.
 *
 * Per-type metadata (morph alias, storage disk, subtype fields, validation
 * rules) is exposed through {@see MediaSubtype}. The unified
 * {@see \App\Domain\Media\MediaTypeRegistry} introspects this model rather
 * than maintaining a separate registry — so adding a new media type only
 * requires a model + migration + one line in config/media.php.
 *
 * @property string     $uuid
 * @property int|null   $pages
 * @property-read Media $media
 */
class Book extends Model implements MediaSubtype
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;
    use HasUuids;

    public const MORPH_ALIAS = 'book';

    protected $table = 'books';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['uuid', 'pages'];

    protected $casts = [
        'pages' => 'integer',
    ];

    /** Always eager-load the media row — title/year/file_path/authors live there. */
    protected $with = ['media'];

    /**
     * @return string[]
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The 1:1 Media row that carries the book's shared metadata (title,
     * publication_year, file_path) and owns the author's relationship.
     */
    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable', 'mediable_type', 'uuid', 'uuid');
    }

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }

    // ---------------------------------------------------------------------
    // MediaSubtype contract
    // ---------------------------------------------------------------------

    public static function getMorphAlias(): string
    {
        return self::MORPH_ALIAS;
    }

    public static function getDisk(): string
    {
        return 'books';
    }

    public static function getSpecificFields(): array
    {
        return ['pages'];
    }

    public static function getValidationRules(): array
    {
        return [
            // 65535 = SMALLINT upper bound — matches the migration column.
            'pages' => ['nullable', 'integer', 'between:1,65535'],
        ];
    }
}
