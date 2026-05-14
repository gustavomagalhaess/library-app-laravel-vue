<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Author\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
