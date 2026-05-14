<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Author\Models\Author;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    public function run(): void
    {
        Author::factory()->count(25)->create();
    }
}
