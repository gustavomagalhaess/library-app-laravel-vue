<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Author\Models\Author;
use App\Domain\Book\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $authorIds = Author::query()->pluck('id')->all();

        Book::factory()
            ->count(50)
            ->create()
            ->each(function (Book $book) use ($authorIds): void {
                // Each book gets 1–3 authors picked at random.
                $picks = collect($authorIds)
                    ->shuffle()
                    ->take(rand(1, 3))
                    ->values()
                    ->all();
                // Authors are attached to the shared Media row (the pivot
                // is media_authors). The factory has already pinned the
                // freshly-created media onto the book via setRelation().
                $book->media->authors()->sync($picks);
            });
    }
}
