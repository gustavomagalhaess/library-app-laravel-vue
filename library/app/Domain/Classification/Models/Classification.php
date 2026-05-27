<?php

namespace App\Domain\Classification\Models;

use App\Domain\Book\Models\Book;
use App\Domain\Media\Models\Media;
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
     * @return BelongsToMany<Media>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Media::class,
            table: 'media_classifications',
            foreignPivotKey: 'classification_id',
            relatedPivotKey: 'media_id',
            parentKey: 'id',
            relatedKey: 'uuid',
        )->where('mediable_type', Book::MORPH_ALIAS);
    }
}
