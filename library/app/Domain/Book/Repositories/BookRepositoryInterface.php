<?php

declare(strict_types=1);

namespace App\Domain\Book\Repositories;

use App\Domain\Book\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookRepositoryInterface
{
    /**
     * Count all books in the system, regardless of any filters or pagination
     */
    public function count(): int;

    /**
     * Paginate books, optionally filtered by a free-text query that matches
     * either the book title or any associated author's name.
     *
     * @return LengthAwarePaginator<Book>
     */
    public function paginate(?string $query, int $perPage = 15): LengthAwarePaginator;

    public function find(string $uuid): ?Book;

    /**
     * Persist a new book together with its Media row and author associations.
     *
     * @param  array{pages?:int|null}                                            $bookAttributes
     * @param  array{title:string, publication_year?:int|null, file_path?:string|null} $mediaAttributes
     * @param  int[]                                                             $authorIds
     */
    public function create(array $bookAttributes, array $mediaAttributes, array $authorIds): Book;

    /**
     * Update a book's attributes (both book-specific and media-level) and
     * optionally re-sync its authors.
     *
     * @param  array{pages?:int|null}                                                              $bookAttributes
     * @param  array{title?:string, publication_year?:int|null, file_path?:string|null}            $mediaAttributes
     * @param  int[]|null                                                                          $authorIds  Pass null to leave authors untouched.
     */
    public function update(Book $book, array $bookAttributes, array $mediaAttributes, ?array $authorIds = null): Book;

    public function delete(Book $book): void;
}
