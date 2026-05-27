<?php

declare(strict_types=1);

namespace App\Domain\Author\Models;

use App\Domain\Book\Models\Book;
use App\Domain\Media\Models\Media;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Aggregate root for the Author bounded context.
 *
 * @property int    $id
 * @property string $name
 */
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    protected $table = 'authors';

    protected $fillable = ['name'];

    /**
     * Books written by this author.
     *
     * @return BelongsToMany<Media>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Media::class,
            table: 'media_authors',
            foreignPivotKey: 'author_id',
            relatedPivotKey: 'media_id',
            parentKey: 'id',
            relatedKey: 'uuid',
        )->where('mediable_type', Book::MORPH_ALIAS);
    }

    protected static function newFactory(): AuthorFactory
    {
        return AuthorFactory::new();
    }
}
