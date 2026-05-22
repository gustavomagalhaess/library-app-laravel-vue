<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Author\Models\Author;
use App\Domain\Book\Models\Book;
use App\Domain\Category\Models\Classification;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $authorIds = Author::query()->pluck('id')->all();
        $classificationIds = Classification::query()->pluck('id')->all();

        Book::factory()
            ->count(50)
            ->create()
            ->each(function (Book $book) use ($authorIds, $classificationIds): void {
                // Each book gets 1–3 authors|classifications picked at random.
                $authorPicks = collect($authorIds)
                    ->shuffle()
                    ->take(rand(1, 3))
                    ->values()
                    ->all();
                $classificationPicks = collect($classificationIds)
                    ->shuffle()
                    ->take(rand(1, 3))
                    ->values()
                    ->all();
                // Authors|Classifications are attached to the shared Media
                // row (the pivot is media_authors). The factory has already
                // pinned the freshly-created media onto the book via
                // setRelation().
                $book->media->authors()->sync($authorPicks);
                $book->media->classifications()->sync($classificationPicks);
            });
    }
}
