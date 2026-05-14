<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for the polymorphic Media front controller.
 *
 * The {type} route parameter (e.g. 'book') is passed as the second argument
 * to every method; the policy maps each (type, action) tuple to the matching
 * Spatie permission and asks the user.
 *
 * Adding a new media type means appending one arm to {@see permissionFor()};
 * the route definitions don't change.
 */
class MediaPolicy
{
    public function view(?User $user, string $type): bool
    {
        return $this->check($user, $type, 'view');
    }

    public function create(?User $user, string $type): bool
    {
        return $this->check($user, $type, 'create');
    }

    public function update(?User $user, string $type): bool
    {
        return $this->check($user, $type, 'update');
    }

    public function delete(?User $user, string $type): bool
    {
        return $this->check($user, $type, 'delete');
    }

    public function download(?User $user, string $type): bool
    {
        return $this->check($user, $type, 'download');
    }

    private function check(?User $user, string $type, string $action): bool
    {
        if ($user === null) {
            return false;
        }

        $permission = $this->permissionFor($type, $action);

        return $permission !== null && $user->can($permission);
    }

    /**
     * Map a media type + action to the Spatie permission name.
     *
     * Permission strings stay pluralised ('books.view') for backwards
     * compatibility with the seeded roles; the type segment of the URL is
     * singular ('book') by convention.
     */
    private function permissionFor(string $type, string $action): ?string
    {
        $prefix = match ($type) {
            'book' => 'books',
            default => null,
        };

        return $prefix === null ? null : $prefix.'.'.$action;
    }
}
