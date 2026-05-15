<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the /profile page plus the Fortify-native profile-information update
 * endpoint (PUT /user/profile-information) and the app's own account-delete
 * endpoint (DELETE /user → DeleteAccountController). The old Breeze
 * ProfileController has been removed; tests are aligned with that.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        // Fortify's UpdateUserProfileInformation action lives at
        // PUT /user/profile-information. On success it redirects back, so we
        // set the Referer via from('/profile') so the redirect resolves there.
        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/user/profile-information', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        // Email changed → Fortify clears email_verified_at and re-sends the
        // verification mail so the user re-verifies the new address.
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/user/profile-information', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        // DeleteAccountController is registered at DELETE /user
        // (route name: current-user.destroy). It validates current_password,
        // logs out, deletes the user, and redirects to /.
        $response = $this
            ->actingAs($user)
            ->delete('/user', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/user', [
                'password' => 'wrong-password',
            ]);

        // DeleteAccountController validates via $request->validate(...) which
        // uses the default error bag, so no third arg here.
        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
