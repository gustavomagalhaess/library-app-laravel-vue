<?php

declare(strict_types=1);

namespace App\Domain\Author\Services;

use App\Domain\Author\Exceptions\AuthorHasBooksException;
use App\Domain\Author\Models\Author;
use App\Domain\Author\Repositories\AuthorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class AuthorService
{
    public function __construct(
        private AuthorRepositoryInterface $authorRepository,
    ) {}

    /**
     * @return LengthAwarePaginator<Author>
     */
    public function list(?string $query = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->authorRepository->paginate($query, $perPage);
    }

    public function find(int $id): ?Author
    {
        return $this->authorRepository->find($id);
    }

    /**
     * @return Collection<int, Author>
     */
    public function search(string $query, int $limit = 10): Collection
    {
        return $this->authorRepository->searchByName($query, $limit);
    }

    public function create(string $name): Author
    {
        return $this->authorRepository->create($name);
    }

    public function update(Author $author, string $name): Author
    {
        return $this->authorRepository->update($author, $name);
    }

    /**
     * @throws AuthorHasBooksException when the author still has associated books.
     */
    public function delete(Author $author): void
    {
        if ($this->authorRepository->hasBooks($author)) {
            throw AuthorHasBooksException::for($author);
        }
        $this->authorRepository->delete($author);
    }
}
