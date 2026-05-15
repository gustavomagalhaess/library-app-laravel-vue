<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        // Fortify exposes the change-password endpoint at PUT /user/password
        // (route name: user-password.update). Breeze used PUT /password.
        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/user/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/user/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        // Fortify's UpdateUserPassword action uses validateWithBag('updatePassword'),
        // so errors land in the named bag — not the default one. Without
        // specifying it, assertSessionHasErrors looks at the default bag and
        // sees nothing.
        $response
            ->assertSessionHasErrors('current_password', null, 'updatePassword')
            ->assertRedirect('/profile');
    }
}
