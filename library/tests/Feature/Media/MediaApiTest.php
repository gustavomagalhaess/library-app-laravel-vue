<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Domain\Author\Models\Author;
use App\Domain\Media\MediaTypeRegistry;
use App\Models\User;
use Database\Factories\BookFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature tests for the polymorphic Media API (POST/POST/DELETE under
 * /api/{type}/...).
 *
 * Every test below is data-provided by {@see mediaTypeProvider()}, so the
 * same assertions run against every registered media subtype. Adding a new
 * type — say, 'movie' — is a one-line change in the provider:
 *
 *     'movie' => [[
 *         'type'            => 'movie',
 *         'factory'         => fn () => MovieFactory::new()->create(),
 *         'subtype'         => ['duration' => 120],
 *         'subtype_updated' => ['duration' => 90],
 *     ]],
 *
 * The provider returns closures (not pre-built models) so the factory call
 * happens *inside* the test transaction — required for RefreshDatabase.
 */
class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function mediaTypeProvider(): array
    {
        return [
            'book' => [[
                'type'            => 'book',
                'factory'         => fn () => BookFactory::new()->create(),
                'subtype'         => ['pages' => 250],
                'subtype_updated' => ['pages' => 500],
            ]],
            // 'movie' => [[
            //     'type'            => 'movie',
            //     'factory'         => fn () => MovieFactory::new()->create(),
            //     'subtype'         => ['duration' => 120],
            //     'subtype_updated' => ['duration' => 90],
            // ]],
        ];
    }

    // ---------------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_authorized_user_can_create_media(array $config): void
    {
        $type = $config['type'];
        $this->fakeDisk($type);
        $this->loginAsRole('admin');

        $author = Author::factory()->create();
        $payload = array_merge([
            'title'            => 'A Wonderful Story',
            'publication_year' => 2024,
            'file'             => UploadedFile::fake()->create('book.pdf', 100, 'application/pdf'),
            'authors'          => ['ids' => [$author->id], 'new' => []],
        ], $config['subtype']);

        $response = $this->postJson("/api/$type", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.media.title', 'A Wonderful Story')
            ->assertJsonPath('data.media.publication_year', 2024)
            ->assertJsonPath('data.media.authors.0.id', $author->id);

        // Shared `media` row exists with the right morph alias.
        $this->assertDatabaseHas('media', [
            'mediable_type'    => $type,
            'title'            => 'A Wonderful Story',
            'publication_year' => 2024,
        ]);

        // Subtype-specific columns landed on the subtype table.
        foreach ($config['subtype'] as $field => $value) {
            $this->assertDatabaseHas($this->tableFor($type), [$field => $value]);
        }

        // The PDF was persisted on the per-type disk.
        $this->assertCount(1, Storage::disk($this->diskFor($type))->files());
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_create_validates_required_fields(array $config): void
    {
        $this->loginAsRole('admin');

        $response = $this->postJson("/api/{$config['type']}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'publication_year', 'file']);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_create_requires_at_least_one_author(array $config): void
    {
        $type = $config['type'];
        $this->fakeDisk($type);
        $this->loginAsRole('admin');

        $payload = array_merge([
            'title'            => 'Loner',
            'publication_year' => 2024,
            'file'             => UploadedFile::fake()->create('book.pdf', 100, 'application/pdf'),
            'authors'          => ['ids' => [], 'new' => []],
        ], $config['subtype']);

        $this->postJson("/api/$type", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['authors']);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_unauthorized_user_cannot_create_media(array $config): void
    {
        $type = $config['type'];
        // Reader can `view`/`download` but not `create` — should be 403 from
        // the route's can:media.create middleware before the request hits the
        // controller body.
        $this->loginAsRole('reader');

        $response = $this->postJson("/api/$type", [
            'title'            => 'Forbidden',
            'publication_year' => 2024,
            'file'             => UploadedFile::fake()->create('book.pdf', 100, 'application/pdf'),
            'authors'          => ['ids' => [], 'new' => ['Anon']],
        ]);

        $response->assertForbidden();
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_guest_cannot_create_media(array $config): void
    {
        // No actingAs — request is unauthenticated. auth:sanctum on the
        // route group converts that to a 401 for JSON requests.
        $this->postJson("/api/{$config['type']}", [])
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_authorized_user_can_update_media(array $config): void
    {
        $type = $config['type'];
        $this->fakeDisk($type);
        $this->loginAsRole('admin');

        /** @var \Illuminate\Database\Eloquent\Model $record */
        $record = ($config['factory'])();
        $author = Author::factory()->create();

        $payload = array_merge([
            'title'            => 'Renamed Title',
            'publication_year' => 1999,
            'authors'          => ['ids' => [$author->id], 'new' => []],
        ], $config['subtype_updated']);

        $response = $this->postJson("/api/$type/{$record->getKey()}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.media.title', 'Renamed Title')
            ->assertJsonPath('data.media.publication_year', 1999);

        $this->assertDatabaseHas('media', [
            'uuid'             => $record->getKey(),
            'title'            => 'Renamed Title',
            'publication_year' => 1999,
        ]);
        foreach ($config['subtype_updated'] as $field => $value) {
            $this->assertDatabaseHas($this->tableFor($type), [
                'uuid'  => $record->getKey(),
                $field  => $value,
            ]);
        }
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_update_returns_404_for_unknown_record(array $config): void
    {
        $this->loginAsRole('admin');

        $response = $this->postJson(
            "/api/{$config['type']}/00000000-0000-0000-0000-000000000000",
            [
                'title'            => 'Renamed',
                'publication_year' => 2024,
                'authors'          => ['ids' => [], 'new' => ['Anon']],
            ],
        );

        $response->assertNotFound();
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_unauthorized_user_cannot_update_media(array $config): void
    {
        $this->loginAsRole('reader');
        $record = ($config['factory'])();

        $response = $this->postJson("/api/{$config['type']}/{$record->getKey()}", [
            'title'            => 'Renamed',
            'publication_year' => 2024,
            'authors'          => ['ids' => [], 'new' => ['Anon']],
        ]);

        $response->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_authorized_user_can_delete_media(array $config): void
    {
        $type = $config['type'];
        $this->fakeDisk($type);
        $this->loginAsRole('admin');
        $record = ($config['factory'])();

        $response = $this->deleteJson("/api/$type/{$record->getKey()}");

        $response->assertNoContent();
        $this->assertDatabaseMissing($this->tableFor($type), ['uuid' => $record->getKey()]);
        $this->assertDatabaseMissing('media',                ['uuid' => $record->getKey()]);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_unauthorized_user_cannot_delete_media(array $config): void
    {
        $this->loginAsRole('reader');
        $record = ($config['factory'])();

        $this->deleteJson("/api/{$config['type']}/{$record->getKey()}")
            ->assertForbidden();

        // Defence-in-depth: the record must still be there.
        $this->assertDatabaseHas($this->tableFor($config['type']), ['uuid' => $record->getKey()]);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_delete_returns_404_for_unknown_record(array $config): void
    {
        $this->loginAsRole('admin');

        $this->deleteJson("/api/{$config['type']}/00000000-0000-0000-0000-000000000000")
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Cross-cutting (no data provider — single request)
    // ---------------------------------------------------------------------

    public function test_unknown_media_type_is_rejected_by_router(): void
    {
        $this->loginAsRole('admin');

        // The route's whereIn(config('media.types')) constraint rejects
        // unknown types before they ever reach MediaController.
        $this->postJson('/api/widget', [])
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Authenticate the test against the Sanctum guard with the given role.
     * The role names line up with RolesAndPermissionsSeeder, which the
     * TestCase base class seeds before every test.
     *
     * We use Laravel's built-in actingAs($user, 'sanctum') rather than
     * Sanctum::actingAs($user) because the latter calls
     * `$user->withAccessToken(...)`, which requires the HasApiTokens trait.
     * Our API is session-only (Sanctum stateful) — there are no personal
     * access tokens in production, so we deliberately don't pull that trait
     * onto the User model.
     */
    private function loginAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    private function diskFor(string $type): string
    {
        return app(MediaTypeRegistry::class)->for($type)->disk;
    }

    private function tableFor(string $type): string
    {
        return app(MediaTypeRegistry::class)->for($type)->table;
    }

    private function fakeDisk(string $type): void
    {
        Storage::fake($this->diskFor($type));
    }
}
