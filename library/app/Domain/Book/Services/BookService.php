<?php

declare(strict_types=1);

namespace App\Domain\Book\Services;

use App\Domain\Author\Models\Author;
use App\Domain\Author\Repositories\AuthorRepositoryInterface;
use App\Domain\Book\Models\Book;
use App\Domain\Book\Repositories\BookRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Application service that orchestrates the Book aggregate's lifecycle.
 *
 * The shared media columns (title, publication_year, file) are validated by
 * StoreMediaRequest/UpdateMediaRequest in the front controller; the service
 * is responsible for any Book-specific validation (currently `pages`) and
 * for the transactional create/update flow across the books, media, and
 * media_authors tables.
 */
final readonly class BookService
{
    public function __construct(
        private BookRepositoryInterface   $bookRepository,
        private AuthorRepositoryInterface $authorRepository,
    ) {}

    public function count(): int
    {
        return $this->bookRepository->count();
    }

    /**
     * @return LengthAwarePaginator<Book>
     */
    public function list(?string $query = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->bookRepository->paginate($query, $perPage);
    }

    public function find(string $uuid): ?Book
    {
        return $this->bookRepository->find($uuid);
    }

    /**
     * @param  array{title:string, publication_year:int, pages?:int|null}  $attributes
     * @param  array{ids?:int[], new?:string[]}                            $authorsInput
     */
    public function create(array $attributes, array $authorsInput, UploadedFile $file): Book
    {
        $bookAttributes = $this->validateBookSpecific($attributes);
        $authorIds = $this->resolveAuthorIds($authorsInput);
        $path = $this->storeFile($file);

        return $this->bookRepository->create(
            bookAttributes: $bookAttributes,
            mediaAttributes: [
                'title'            => $attributes['title'],
                'publication_year' => $attributes['publication_year'],
                'file_path'        => $path,
            ],
            authorIds: $authorIds,
        );
    }

    /**
     * @param  array{title?:string, publication_year?:int, pages?:int|null}  $attributes
     * @param  array{ids?:int[], new?:string[]}|null                         $authorsInput
     */
    public function update(Book $book, array $attributes, ?array $authorsInput, ?UploadedFile $file): Book
    {
        $bookAttributes = $this->validateBookSpecific($attributes);

        $mediaAttributes = array_filter(
            [
                'title'            => $attributes['title'] ?? null,
                'publication_year' => $attributes['publication_year'] ?? null,
            ],
            static fn ($v) => $v !== null,
        );

        if ($file instanceof UploadedFile) {
            $newPath = $this->storeFile($file);
            $previous = $book->media?->file_path;
            if ($previous && Storage::disk('books')->exists($previous)) {
                Storage::disk('books')->delete($previous);
            }
            $mediaAttributes['file_path'] = $newPath;
        }

        $authorIds = $authorsInput !== null ? $this->resolveAuthorIds($authorsInput) : null;

        return $this->bookRepository->update($book, $bookAttributes, $mediaAttributes, $authorIds);
    }

    public function delete(Book $book): void
    {
        $path = $book->media?->file_path;
        if ($path && Storage::disk('books')->exists($path)) {
            Storage::disk('books')->delete($path);
        }
        $this->bookRepository->delete($book);
    }

    /**
     * Pull the book-specific fields out of the request payload and validate
     * them here (per the spec: "the specific fields for each media type
     * should be validated in their respective services").
     *
     * @param  array<string,mixed>  $attributes
     * @return array{pages?:int|null}
     */
    private function validateBookSpecific(array $attributes): array
    {
        $bookOnly = array_intersect_key($attributes, array_flip(['pages']));

        if ($bookOnly === []) {
            return [];
        }

        $validator = Validator::make($bookOnly, [
            'pages' => ['nullable', 'integer', 'between:1,65535'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Combine pre-existing author IDs with any newly-created author names.
     *
     * @param  array{ids?:int[], new?:string[]}  $input
     * @return int[]
     */
    private function resolveAuthorIds(array $input): array
    {
        $ids = array_values(array_unique(array_map('intval', $input['ids'] ?? [])));

        foreach ($input['new'] ?? [] as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            /** @var Author $author */
            $author = $this->authorRepository->create($name);
            $ids[] = $author->id;
        }

        return array_values(array_unique($ids));
    }

    private function storeFile(UploadedFile $file): string
    {
        // Store under the books disk; returns the relative path saved on disk.
        return $file->store('/', 'books');
    }
}
