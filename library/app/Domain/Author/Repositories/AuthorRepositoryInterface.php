<?php

declare(strict_types=1);

namespace App\Domain\Author\Repositories;

use App\Domain\Author\Models\Author;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuthorRepositoryInterface
{
    /**
     * Paginate authors, optionally filtered by name.
     *
     * @return LengthAwarePaginator<Author>
     */
    public function paginate(?string $query, int $perPage = 10): LengthAwarePaginator;

    public function find(int $id): ?Author;

    /**
     * Lightweight name search used by the auto-complete dropdown.
     *
     * @return Collection<int, Author>
     */
    public function searchByName(string $query, int $limit = 10): Collection;

    public function create(string $name): Author;

    public function update(Author $author, string $name): Author;

    public function delete(Author $author): void;

    public function hasBooks(Author $author): bool;
}
