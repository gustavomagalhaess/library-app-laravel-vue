<?php

declare(strict_types=1);

namespace App\Domain\Media\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Parametric repository for any media subtype.
 *
 * Every method takes a `$type` morph alias (the {type} from the URL) so the
 * concrete subtype model can be resolved lazily from the registry. There is
 * deliberately no `MediaRepositoryInterface::find()` overload per type — one
 * implementation handles all of them.
 */
interface MediaRepositoryInterface
{
    public function count(string $type): int;

    /**
     * @return LengthAwarePaginator<Model>
     */
    public function paginate(string $type, ?string $query, int $perPage = 10): LengthAwarePaginator;

    public function find(string $type, string $uuid): ?Model;

    /**
     * @param  array<string, mixed>                              $subtypeAttributes  Columns living on the subtype's own table.
     * @param  array{title?:string, publication_year?:int|null, file_path?:string|null}  $mediaAttributes
     * @param  int[]                                             $authorIds
     */
    public function create(
        string $type,
        array $subtypeAttributes,
        array $mediaAttributes,
        array $authorIds,
    ): Model;

    /**
     * @param  array<string, mixed>                              $subtypeAttributes
     * @param  array{title?:string, publication_year?:int|null, file_path?:string|null}  $mediaAttributes
     * @param  int[]|null                                        $authorIds  Pass null to leave authors untouched.
     */
    public function update(
        string $type,
        Model $record,
        array $subtypeAttributes,
        array $mediaAttributes,
        ?array $authorIds = null,
    ): Model;

    public function delete(Model $record): void;
}
