<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@library.local'],
            [
                'name' => 'Library Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(['admin']);

        $librarian = User::updateOrCreate(
            ['email' => 'librarian@library.local'],
            [
                'name' => 'Library Librarian',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $librarian->syncRoles(['librarian']);

        $reader = User::updateOrCreate(
            ['email' => 'reader@library.local'],
            [
                'name' => 'Library Reader',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $reader->syncRoles(['reader']);
    }
}
