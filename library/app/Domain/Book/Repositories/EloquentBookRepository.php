<?php

declare(strict_types=1);

namespace App\Domain\Book\Repositories;

use App\Domain\Book\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentBookRepository implements BookRepositoryInterface
{
    public function count(): int
    {
        return Book::count();
    }

    public function paginate(?string $query, int $perPage = 15): LengthAwarePaginator
    {
        return Book::query()
            // Always pull the morph parent + authors so the JSON payload
            // includes them without extra queries.
            ->with('media.authors:id,name')
            // Title filter lives on the morph parent (media.title), so we
            // only join when there's an actual free-text query to evaluate.
            ->when($query, function (Builder $q, string $term): void {
                $like = '%'.$term.'%';
                $q->join('media', 'media.uuid', '=', 'books.uuid')
                    ->select('books.*')
                    ->where(function (Builder $inner) use ($like): void {
                        $inner->where('media.title', 'like', $like)
                            // Authors are attached to media, so the existence
                            // check goes through the media relation.
                            ->orWhereHas('media.authors', fn (Builder $a) => $a->where('name', 'like', $like));
                    });
            })
            ->orderByDesc('books.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(string $uuid): ?Book
    {
        return Book::with('media.authors:id,name')->find($uuid);
    }

    public function create(array $bookAttributes, array $mediaAttributes, array $authorIds): Book
    {
        return DB::transaction(function () use ($bookAttributes, $mediaAttributes, $authorIds): Book {
            // Create the Book first so it owns the canonical UUID, then create
            // the matching Media row through the morphOne relationship so
            // Laravel auto-fills `mediable_type` from the morph map and the
            // shared `uuid` column. Authors are attached on the Media side
            // because the pivot is `media_authors`.
            $book = Book::create($bookAttributes);
            $media = $book->media()->create($mediaAttributes);
            $media->authors()->sync($authorIds);

            return $book->load('media.authors:id,name');
        });
    }

    public function update(Book $book, array $bookAttributes, array $mediaAttributes, ?array $authorIds = null): Book
    {
        return DB::transaction(function () use ($book, $bookAttributes, $mediaAttributes, $authorIds): Book {
            if ($bookAttributes !== []) {
                $book->fill($bookAttributes)->save();
            }

            if ($mediaAttributes !== []) {
                if ($book->media === null) {
                    // Belt-and-braces: every book should have a media row,
                    // but if a fixture/seed skipped it, create it now.
                    $book->media()->create($mediaAttributes);
                } else {
                    $book->media->fill($mediaAttributes)->save();
                }
            }

            if ($authorIds !== null) {
                // Make sure media is loaded after a possible create above.
                $book->loadMissing('media');
                $book->media?->authors()->sync($authorIds);
            }

            return $book->load('media.authors:id,name');
        });
    }

    public function delete(Book $book): void
    {
        DB::transaction(function () use ($book): void {
            // The media_authors pivot rows for this UUID cascade away on
            // either side (FK on `media_id` cascades when we delete media;
            // FK on `author_id` cascades when an author is removed), so we
            // only need to delete the media row and the book row itself.
            $book->media?->delete();
            $book->delete();
        });
    }
}
