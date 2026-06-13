<?php

declare(strict_types=1);

namespace App\Domain\Media\Models;

use App\Domain\Author\Models\Author;
use App\Domain\Classification\Models\Classification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Morph parent that owns the columns shared by every media type
 * (title, publication_year, file_path).
 *
 * The morph relationship is keyed by `uuid` (not the conventional
 * `mediable_id`) so a single id space is shared across all subtype tables —
 * a book and a movie can never collide because UUIDs are globally unique.
 *
 * @property string $uuid
 * @property string $mediable_type
 * @property string $title
 * @property int|null $publication_year
 * @property string|null $file_path
 */
class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'mediable_type',
        'title',
        'publication_year',
        'file_path',
    ];

    protected $casts = [
        'publication_year' => 'integer',
    ];

    /**
     * Tell HasUuids that the UUID column is `uuid`, not the default `id`.
     *
     * @return string[]
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Morph back to the concrete subtype model (Book, future Movie, …).
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo('mediable', 'mediable_type', 'uuid', 'uuid');
    }

    /**
     * @return BelongsToMany<Author>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Author::class,
            table: 'media_authors',
            foreignPivotKey: 'media_id',
            relatedPivotKey: 'author_id',
            parentKey: 'uuid',
            relatedKey: 'id',
        );
    }

    /**
     * @return BelongsToMany<Classification>
     */
    public function classifications(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Classification::class,
            table: 'media_classifications',
            foreignPivotKey: 'media_id',
            relatedPivotKey: 'classification_id',
            parentKey: 'uuid',
            relatedKey: 'id',
        );
    }
}
