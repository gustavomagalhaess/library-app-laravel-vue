<?php

declare(strict_types=1);

namespace App\Domain\Book\Models;

use App\Domain\Media\Models\Media;
use App\Domain\ModelInterface;
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
 * @property string     $uuid
 * @property int|null   $pages
 * @property-read Media $media
 */
class Book extends Model implements ModelInterface
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
     * publication_year, file_path) and owns the authors relationship.
     *
     * morphOne args: related, name, type column on related,
     * id column on related (uuid), local key on this model (uuid).
     */
    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable', 'mediable_type', 'uuid', 'uuid');
    }

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }

    static public function getSpecificFields(): array
    {
        return ['pages'];
    }
}
