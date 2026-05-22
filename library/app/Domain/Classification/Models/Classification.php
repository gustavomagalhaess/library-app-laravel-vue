<?php

namespace App\Domain\Classification\Models;

use App\Domain\Book\Models\Book;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Aggregate root for the Classification bounded context.
 *
 * @property $id
 * @property $code
 * @property $name
 */
class Classification extends Model
{
    protected $table = 'classifications';

    /**
     * Books that belongs to this classification.
     *
     * The pivot is the shared `media_classifications` table (keyed by media UUID).
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
            table: 'book_classifications',
            foreignPivotKey: 'classification_id',
            relatedPivotKey: 'book_id',
            parentKey: 'id',
            relatedKey: 'id',
        );
    }
}
