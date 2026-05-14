<?php

declare(strict_types=1);

namespace App\Domain\Author\Models;

use App\Domain\Book\Models\Book;
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
     * The pivot is the shared `media_authors` table (keyed by media UUID).
     * Joining onto `books.uuid` automatically restricts results to book rows
     * — pivot entries for other media types (movies, music, …) won't surface
     * here because their UUIDs don't exist in the `books` table.
     *
     * @return BelongsToMany<Book>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Book::class,
            table: 'media_authors',
            foreignPivotKey: 'author_id',
            relatedPivotKey: 'media_id',
            parentKey: 'id',
            relatedKey: 'uuid',
        );
    }

    protected static function newFactory(): AuthorFactory
    {
        return AuthorFactory::new();
    }
}
