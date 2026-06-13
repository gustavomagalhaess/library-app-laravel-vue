<?php

declare(strict_types=1);

namespace Tests\Browser\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end browser coverage of the Fortify login flow.
 *
 * Each test gets a clean DB via DatabaseMigrations, then we seed only the
 * Spatie roles/permissions registry (RolesAndPermissionsSeeder is idempotent
 * and cheap) and create the user fresh via the factory with a known
 * password — that way the test never depends on UserSeeder having run with
 * a particular password, and it stays meaningful even if the seeded
 * fixtures change.
 *
 * The form selectors (#email, #password, the "Log in" button text) all
 * come straight from resources/js/Pages/Auth/Login.vue — keep this in
 * sync if you rename the inputs.
 */
class LoginTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test Reader',
            'email' => 'tester@library.local',
            // UserFactory defaults password to "password", so we don't
            // override it here — the @factory's static $password is
            // re-used across the run and we want hashing to stay cheap.
        ]);
        $user->assignRole('reader');

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->visit('/login')
                // The form is rendered by Vue after hydration — visit()
                // returns as soon as the document loads, well before Vue
                // mounts. Wait for the actual input before touching it.
                ->waitFor('#email')
                ->assertSee('Email')
                ->assertSee('Password')
                ->type('#email', $user->email)
                ->type('#password', 'password')
                ->press('LOG IN')
                // Fortify's `home` config sends successful logins to
                // /dashboard (see config/fortify.php).
                ->waitForLocation('/dashboard')
                ->assertPathIs('/dashboard')
                // The authenticated layout renders the user's name in the
                // top-right as a link to the profile page — confirming
                // we're logged in as THIS user, not just any user.
                ->waitForText($user->name)
                ->assertSee($user->name)
                ->assertSee('Dashboard');
        });
    }

    public function test_user_cannot_log_in_with_invalid_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'tester@library.local',
        ]);
        $user->assignRole('reader');

        $this->browse(function (Browser $browser) use ($user): void {
            // Dusk reuses the same browser instance across tests in this
            // class, so cookies from the preceding "valid credentials"
            // test would still authenticate this session. visiting /login
            // while authenticated bounces to /dashboard via Fortify's
            // RedirectIfAuthenticated middleware, and then waitFor('#email')
            // times out (the dashboard has no email input). Wipe cookies
            // first so we always start unauthenticated.
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/login')
                ->waitFor('#email')
                ->type('#email', $user->email)
                ->type('#password', 'definitely-not-the-password')
                ->press('LOG IN')
                // On a failed credential check Fortify stays on /login
                // (Inertia handles the 422 in-place) and surfaces the
                // auth.failed translation string via the email field's
                // InputError. The session round-trip needs a beat for
                // Vue to re-render with the new errors prop.
                ->assertPathIs('/login')
                ->waitForText('These credentials do not match our records.')
                ->assertSee('These credentials do not match our records.');
        });
    }

    public function test_login_validation_errors_appear_for_empty_submission(): void
    {
        $this->browse(function (Browser $browser): void {
            // The email + password inputs in Login.vue carry the HTML5
            // `required` attribute, so submitting the form with empty
            // fields is blocked by the browser before Fortify ever sees
            // the request. We confirm the form never navigated away.
            $browser->visit('/login')
                ->waitFor('#email')
                ->press('LOG IN')
                ->pause(250) // give the form a tick to attempt submission
                ->assertPathIs('/login')
                ->assertPresent('#email[required]')
                ->assertPresent('#password[required]');
        });
    }
}
