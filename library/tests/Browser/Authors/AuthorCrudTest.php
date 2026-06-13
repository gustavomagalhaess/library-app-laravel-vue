<?php

declare(strict_types=1);

namespace Tests\Browser\Authors;

use App\Domain\Author\Models\Author;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end browser coverage of the author create / update / delete flows.
 *
 * Permission matrix exercised here:
 *   admin     — authors.create ✓  authors.update ✓  authors.delete ✓
 *   librarian — authors.create ✓  authors.update ✓  authors.delete ✗
 *   reader    — authors.create ✗  authors.update ✗  authors.delete ✗
 *
 * Environment assumptions match BookCrudTest:
 *   - QUEUE_CONNECTION=sync (.env.dusk.local) → jobs run inline, toast
 *     resolves after the first poll (~750 ms).
 *   - DatabaseMigrations resets the DB before each test.
 *   - Cookie cleanup before every login prevents session bleed-over.
 */
class AuthorCrudTest extends DuskTestCase
{
    use DatabaseMigrations;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loginAs(Browser $browser, string $role = 'admin'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitFor('#email')
            ->type('#email', $user->email)
            ->type('#password', 'password')
            ->press('LOG IN')
            ->waitForLocation('/dashboard');

        return $user;
    }

    // -------------------------------------------------------------------------
    // Admin CRUD
    // -------------------------------------------------------------------------

    public function test_admin_can_create_an_author(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAs($browser);

            $browser->visit('/authors')
                ->waitFor('@author-new')
                ->click('@author-new')
                ->waitFor('@author-name')
                ->type('@author-name', 'Jane Austen')
                ->click('@author-submit')
                ->waitForText('"Jane Austen" added.', 10)
                ->assertSee('Jane Austen');
        });
    }

    public function test_admin_can_update_an_author(): void
    {
        $author = Author::factory()->create(['name' => 'Old Name']);

        $this->browse(function (Browser $browser) use ($author): void {
            $this->loginAs($browser);

            $browser->visit('/authors')
                ->waitFor('@author-edit-'.$author->id)
                ->click('@author-edit-'.$author->id)
                ->waitFor('@author-name')
                ->type('@author-name', 'New Name')
                ->click('@author-submit')
                ->waitForText('"New Name" updated.', 10)
                ->assertSee('New Name');
        });
    }

    public function test_admin_can_delete_an_author(): void
    {
        // Author has no books — the job's precondition check passes.
        $author = Author::factory()->create();
        $name = $author->name;

        $this->browse(function (Browser $browser) use ($author, $name): void {
            $this->loginAs($browser);

            $browser->visit('/authors')
                ->waitFor('@author-delete-'.$author->id)
                ->click('@author-delete-'.$author->id)
                ->waitFor('@confirm-modal-confirm')
                ->click('@confirm-modal-confirm')
                ->waitForText('"'.$name.'" was deleted.', 10)
                ->waitUntil(
                    '!document.querySelector(\'tbody\').innerText.includes('.json_encode($name).')',
                    10,
                );
        });
    }

    // -------------------------------------------------------------------------
    // Librarian — can create and update, cannot delete
    // -------------------------------------------------------------------------

    public function test_librarian_can_create_an_author(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAs($browser, 'librarian');

            $browser->visit('/authors')
                ->waitFor('@author-new')
                ->click('@author-new')
                ->waitFor('@author-name')
                ->type('@author-name', 'Virginia Woolf')
                ->click('@author-submit')
                ->waitForText('"Virginia Woolf" added.', 10)
                ->assertSee('Virginia Woolf');
        });
    }

    public function test_librarian_can_update_an_author(): void
    {
        $author = Author::factory()->create(['name' => 'Old Name']);

        $this->browse(function (Browser $browser) use ($author): void {
            $this->loginAs($browser, 'librarian');

            $browser->visit('/authors')
                ->waitFor('@author-edit-'.$author->id)
                ->click('@author-edit-'.$author->id)
                ->waitFor('@author-name')
                ->type('@author-name', 'New Name')
                ->click('@author-submit')
                ->waitForText('"New Name" updated.', 10)
                ->assertSee('New Name');
        });
    }

    public function test_librarian_cant_delete_an_author(): void
    {
        $author = Author::factory()->create();

        $this->browse(function (Browser $browser) use ($author): void {
            $this->loginAs($browser, 'librarian');

            $browser->visit('/authors')
                ->waitFor('@author-edit-'.$author->id)
                ->assertMissing('@author-delete-'.$author->id);
        });
    }

    // -------------------------------------------------------------------------
    // Reader — view only, no mutations
    // -------------------------------------------------------------------------

    public function test_reader_cant_create_an_author(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAs($browser, 'reader');

            $browser->visit('/authors')
                ->waitForText('Authors')
                ->assertMissing('@author-new');
        });
    }

    public function test_reader_cant_update_an_author(): void
    {
        $author = Author::factory()->create();

        $this->browse(function (Browser $browser) use ($author): void {
            $this->loginAs($browser, 'reader');

            $browser->visit('/authors')
                ->waitForText($author->name)
                ->assertMissing('@author-edit-'.$author->id);
        });
    }

    public function test_reader_cant_delete_an_author(): void
    {
        $author = Author::factory()->create();

        $this->browse(function (Browser $browser) use ($author): void {
            $this->loginAs($browser, 'reader');

            $browser->visit('/authors')
                ->waitForText($author->name)
                ->assertMissing('@author-delete-'.$author->id);
        });
    }
}
