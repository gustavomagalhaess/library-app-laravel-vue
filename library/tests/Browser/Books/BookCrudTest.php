<?php

declare(strict_types=1);

namespace Tests\Browser\Books;

use App\Domain\Book\Models\Book;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end browser coverage of the book create / update / delete flows.
 *
 * Permission matrix exercised here:
 *    admin     — books.create ✓  books.update ✓  books.delete ✓
 *    librarian — books.create ✓  books.update ✓  books.delete ✗
 *    reader    — books.create ✗  books.update ✗  books.delete ✗
 *
 * Notes on environment assumptions:
 *
 * - QUEUE_CONNECTION=sync (.env.dusk.local) so every dispatched job runs
 *   inline inside the HTTP handler. By the time the 202 comes back the job
 *   is already completed, so the SPA's first poll resolves immediately and
 *   the success toast appears within ~750 ms.
 *
 * - DatabaseMigrations resets the DB before each test. Roles are seeded
 *   fresh at the top of each test (the seeder is findOrCreate-based and
 *   idempotent). A librarian user is created via factory with a known
 *   password so we never depend on UserSeeder fixtures.
 *
 * - Cookie cleanup before every login prevents session bleed-over between
 *   tests (Dusk reuses one browser instance per class).
 */
class BookCrudTest extends DuskTestCase
{
    use DatabaseMigrations;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loginAs(Browser $browser, ?string $role = 'admin'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        // Wipe any cookie from a previous test so we always start anonymous.
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
    // Tests
    // -------------------------------------------------------------------------

    public function test_admin_can_create_a_book(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAs($browser);

            $browser->visit('/book')
                // Wait for the Vue component to hydrate and render the New button.
                ->waitFor('@book-new')
                ->click('@book-new')
                // The form is rendered inside a <dialog> by Modal.vue — wait
                // for a field before interacting.
                ->waitFor('@book-title')
                ->type('@book-title', 'Dusk Test Book')
                ->type('@book-year', '2024')
                // Add a new author: type 3+ chars then press Enter to trigger
                // the @keydown.enter handler (addNewAuthor).
                ->type('@book-author-input', 'Test Author')
                ->keys('@book-author-input', WebDriverKeys::ENTER)
                ->type('@book-pages', '200')
                // Attach a minimal valid PDF — file is required on create.
                ->attach('input[type="file"]', base_path('tests/Browser/fixtures/sample.pdf'))
                ->click('@book-submit')
                // The SPA closes the modal immediately, shows a loading toast,
                // then resolves it once the sync job completes (~750 ms).
                ->waitForText('"Dusk Test Book" added.', 10)
                ->assertSee('Dusk Test Book');
        });
    }

    public function test_admin_can_update_a_book(): void
    {
        $book = Book::factory()->create();

        $this->browse(function (Browser $browser) use ($book): void {
            $this->loginAs($browser);

            $browser->visit('/book')
                // Target the specific row's edit button by its dusk attribute.
                ->waitFor('@book-edit-'.$book->uuid)
                ->click('@book-edit-'.$book->uuid)
                ->waitFor('@book-title')
                // type() clears the field before typing, replacing the existing title.
                ->type('@book-title', 'Updated Book Title')
                // The factory creates books without authors on media_authors.
                // UpdateMediaRequest requires at least one author, so we add one.
                ->type('@book-author-input', 'Test Author')
                ->keys('@book-author-input', WebDriverKeys::ENTER)
                ->click('@book-submit')
                ->waitForText('"Updated Book Title" updated.', 10)
                ->assertSee('Updated Book Title');
        });
    }

    public function test_admin_can_delete_a_book(): void
    {
        $book = Book::factory()->create();
        $title = $book->media->title;

        $this->browse(function (Browser $browser) use ($book, $title): void {
            $this->loginAs($browser);

            $browser->visit('/book')
                ->waitFor('@book-delete-'.$book->uuid)
                ->click('@book-delete-'.$book->uuid)
                // ConfirmModal appears — wait for the confirm button.
                ->waitFor('@confirm-modal-confirm')
                ->click('@confirm-modal-confirm')
                ->waitForText('"'.$title.'" was deleted.', 10)
                // Wait for the Inertia partial-reload to remove the row from the
                // table. assertDontSee would be synchronous and could race;
                // waitUntil polls until the tbody no longer contains the title.
                // The toast briefly holds the title too, so we scope to tbody.
                ->waitUntil(
                    '!document.querySelector(\'tbody\').innerText.includes('.json_encode($title).')',
                    10,
                );
        });
    }

    public function test_librarian_cant_delete_a_book(): void
    {
        $book = Book::factory()->create();

        $this->browse(function (Browser $browser) use ($book): void {
            $this->loginAs($browser, 'librarian');

            $browser->visit('/book')->assertMissing('@book-delete-'.$book->uuid);
        });
    }

    public function test_reader_cant_create_a_book(): void
    {
        $book = Book::factory()->create();

        $this->browse(function (Browser $browser): void {
            $this->loginAs($browser, 'reader');

            $browser->visit('/book')->assertMissing('@book-new');
        });
    }

    public function test_reader_cant_update_a_book(): void
    {
        $book = Book::factory()->create();

        $this->browse(function (Browser $browser) use ($book): void {
            $this->loginAs($browser, 'reader');

            $browser->visit('/book')->assertMissing('@book-edit-'.$book->uuid);
        });
    }

    public function test_reader_cant_delete_a_book(): void
    {
        $book = Book::factory()->create();

        $this->browse(function (Browser $browser) use ($book): void {
            $this->loginAs($browser, 'reader');

            $browser->visit('/book')->assertMissing('@book-delete-'.$book->uuid);
        });
    }
}
