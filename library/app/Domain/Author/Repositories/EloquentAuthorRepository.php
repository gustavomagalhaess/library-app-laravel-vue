<?php

declare(strict_types=1);

namespace App\Domain\Author\Repositories;

use App\Domain\Author\Models\Author;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentAuthorRepository implements AuthorRepositoryInterface
{
    public function paginate(?string $query, int $perPage = 15): LengthAwarePaginator
    {
        return Author::query()
            ->withCount('books')
            ->when($query, fn (Builder $q, string $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Author
    {
        return Author::withCount('books')->find($id);
    }

    public function searchByName(string $query, int $limit = 10): Collection
    {
        return Author::query()
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name']);
    }

    public function create(string $name): Author
    {
        return Author::create(['name' => $name]);
    }

    public function update(Author $author, string $name): Author
    {
        $author->fill(['name' => $name])->save();
        return $author;
    }

    public function delete(Author $author): void
    {
        $author->delete();
    }

    public function hasBooks(Author $author): bool
    {
        return $author->books()->exists();
    }
}
