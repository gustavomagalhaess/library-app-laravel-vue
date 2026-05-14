<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles/permissions so this seeder is idempotent.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Books
            'books.view',
            'books.create',
            'books.update',
            'books.delete',
            'books.download',
            // Authors
            'authors.view',
            'authors.create',
            'authors.update',
            'authors.delete',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions($permissions);

        $librarian = Role::findOrCreate('librarian', 'web');
        $librarian->syncPermissions([
            'books.view', 'books.create', 'books.update', 'books.download',
            'authors.view', 'authors.create', 'authors.update',
        ]);

        $reader = Role::findOrCreate('reader', 'web');
        $reader->syncPermissions([
            'books.view', 'books.download', 'authors.view',
        ]);
    }
}
