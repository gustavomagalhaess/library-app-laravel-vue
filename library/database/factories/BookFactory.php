<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Book\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * Book itself only owns the book-specific columns; HasUuids fills `uuid`
     * automatically.
     */
    public function definition(): array
    {
        return [
            'pages' => fake()->numberBetween(80, 900),
        ];
    }

    public function configure(): static
    {
        // Each Book needs a matching `media` row (shared UUID) — create it
        // through the morphOne relationship so Laravel auto-fills the
        // `mediable_type` (via the morph map) and `uuid` columns for us.
        // We also pin the relation onto the in-memory model so the seeder
        // (and any caller that holds the factory result) can reach
        // `$book->media->authors()` without an extra round-trip.
        return $this->afterCreating(function (Book $book): void {
            $media = $book->media()->create([
                'title' => fake()->sentence(rand(2, 5)),
                'publication_year' => fake()->numberBetween(1900, (int) date('Y')),
                'file_path' => 'sample-'.fake()->unique()->slug(2).'.pdf',
            ]);
            $book->setRelation('media', $media);
        });
    }
}
