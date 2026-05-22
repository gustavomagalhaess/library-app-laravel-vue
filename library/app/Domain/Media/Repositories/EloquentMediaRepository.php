<?php

declare(strict_types=1);

namespace App\Domain\Media\Repositories;

use App\Domain\Media\MediaTypeRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single Eloquent implementation that handles every registered media subtype.
 *
 * The repository never references a concrete subtype class — it asks the
 * {@see MediaTypeRegistry} for the model class and table name of `$type` and
 * then drives Eloquent against the resolved class. That keeps the entire CRUD
 * surface (paginate / find / create / update / delete) generic.
 *
 * The structural assumptions baked in:
 *   - The subtype's primary key is `uuid` and matches `media.uuid` 1:1.
 *   - The subtype owns a `media()` morphOne relation to the morph parent.
 *   - The morph parent owns `authors()` (the `media_authors` pivot).
 * These hold for every model implementing {@see \App\Domain\Media\Contracts\MediaSubtype}.
 */
final readonly class EloquentMediaRepository implements MediaRepositoryInterface
{
    public function __construct(
        private MediaTypeRegistry $registry,
    ) {}

    public function count(string $type): int
    {
        $modelClass = $this->registry->for($type)->modelClass;

        return $modelClass::query()->count();
    }

    public function paginate(string $type, ?string $query, int $perPage = 15): LengthAwarePaginator
    {
        $definition = $this->registry->for($type);
        $modelClass = $definition->modelClass;
        $table = $definition->table;

        return $modelClass::query()
            // Always pull the morph parent, authors, and classifications so
            // the JSON payload includes them without extra queries.
            ->with(['media.authors:id,name', 'media.classifications:id,code,name'])
            // Title filter lives on the morph parent (media.title), so we
            // only join when there's an actual free-text query to evaluate.
            ->when($query, function (Builder $q, string $term) use ($table): void {
                $like = '%'.$term.'%';
                $q->join('media', 'media.uuid', '=', $table.'.uuid')
                    ->select($table.'.*')
                    ->where(function (Builder $inner) use ($like): void {
                        $inner->whereLike('media.title', $like)
                            // Authors are attached to media, so the existence
                            // check goes through the media relation.
                            ->orWhereHas('media.authors', fn (Builder $a) => $a->whereLike('name', $like));
                    });
            })
            ->orderByDesc($table.'.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(string $type, string $uuid): ?Model
    {
        $modelClass = $this->registry->for($type)->modelClass;

        return $modelClass::query()->with(['media.authors:id,name', 'media.classifications:id,code,name'])->find($uuid);
    }

    public function create(string $type, array $subtypeAttributes, array $mediaAttributes, array $authorIds, array $classificationIds = []): Model
    {
        $modelClass = $this->registry->for($type)->modelClass;

        return DB::transaction(function () use ($modelClass, $subtypeAttributes, $mediaAttributes, $authorIds, $classificationIds): Model {
            // Create the subtype first so it owns the canonical UUID, then
            // create the matching Media row through the morphOne relationship
            // so Laravel auto-fills `mediable_type` from the morph map and
            // reuses the subtype's `uuid`. Authors and classifications are
            // attached on the Media side via their respective pivot tables.
            /** @var Model $record */
            $record = $modelClass::query()->create($subtypeAttributes);
            $media = $record->media()->create($mediaAttributes);
            $media->authors()->sync($authorIds);
            $media->classifications()->sync($classificationIds);

            return $record->load(['media.authors:id,name', 'media.classifications:id,code,name']);
        });
    }

    public function update(
        string $type,
        Model $record,
        array $subtypeAttributes,
        array $mediaAttributes,
        ?array $authorIds = null,
        ?array $classificationIds = null,
    ): Model {
        // $type is unused at runtime here — the model already knows what it is
        // — but the parameter is kept for symmetry with create() and to leave
        // room for future per-type behaviour (e.g. movie-specific cascades).
        unset($type);

        return DB::transaction(function () use ($record, $subtypeAttributes, $mediaAttributes, $authorIds, $classificationIds): Model {
            if ($subtypeAttributes !== []) {
                $record->fill($subtypeAttributes)->save();
            }

            if ($mediaAttributes !== []) {
                if ($record->media === null) {
                    // Belt-and-braces: every subtype row should have a media
                    // row, but if a fixture/seed skipped it, create it now.
                    $record->media()->create($mediaAttributes);
                } else {
                    $record->media->fill($mediaAttributes)->save();
                }
            }

            // Make sure media is loaded before touching either pivot.
            if ($authorIds !== null || $classificationIds !== null) {
                $record->loadMissing('media');
            }

            if ($authorIds !== null) {
                $record->media?->authors()->sync($authorIds);
            }

            if ($classificationIds !== null) {
                $record->media?->classifications()->sync($classificationIds);
            }

            return $record->load(['media.authors:id,name', 'media.classifications:id,code,name']);
        });
    }

    public function delete(Model $record): void
    {
        DB::transaction(function () use ($record): void {
            // The media_authors pivot rows cascade away on both sides (FK on
            // `media_id` cascades when we delete media; FK on `author_id`
            // cascades when an author is removed), so we only need to delete
            // the media row and the subtype row itself.
            $record->media?->delete();
            $record->delete();
        });
    }
}
