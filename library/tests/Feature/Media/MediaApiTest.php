<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Domain\Author\Models\Author;
use App\Domain\Jobs\Models\TrackedJob;
use App\Domain\Media\MediaTypeRegistry;
use App\Models\User;
use Database\Factories\BookFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Feature tests for the polymorphic Media API (POST/POST/DELETE under
 * /api/{type}/...).
 *
 * Every test below is data-provided by {@see mediaTypeProvider()}, so the
 * same assertions run against every registered media subtype. Adding a new
 * type — say, 'movie' — is a one-line change in the provider.
 *
 * Since every mutation now goes through the queued pipeline, controller
 * responses are 202 + a TrackedJob descriptor (not the persisted row). In
 * the test environment QUEUE_CONNECTION=sync (see phpunit.xml), so the job
 * has already executed by the time the HTTP call returns — we assert on
 * the TrackedJob's final `status` and on the underlying DB state.
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

        $response->assertStatus(Response::HTTP_ACCEPTED)
            ->assertJsonPath('job.type', 'media.create')
            ->assertJsonStructure(['job' => ['id', 'status', 'type']]);

        // sync queue → job has already executed before the response returned.
        $job = TrackedJob::where('uuid', $response->json('job.id'))->firstOrFail();
        $this->assertSame(TrackedJob::STATUS_COMPLETED, $job->status, $job->message ?? '');
        $this->assertSame('A Wonderful Story', $job->result['record']['media']['title'] ?? null);

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

        // Validation runs synchronously in the FormRequest — no job should
        // be dispatched / TrackedJob row written.
        $this->assertDatabaseCount('tracked_jobs', 0);
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
        $this->loginAsRole('reader');

        $response = $this->postJson("/api/$type", [
            'title'            => 'Forbidden',
            'publication_year' => 2024,
            'file'             => UploadedFile::fake()->create('book.pdf', 100, 'application/pdf'),
            'authors'          => ['ids' => [], 'new' => ['Anon']],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('tracked_jobs', 0);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_guest_cannot_create_media(array $config): void
    {
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

        $response->assertStatus(Response::HTTP_ACCEPTED)
            ->assertJsonPath('job.type', 'media.update');

        $job = TrackedJob::where('uuid', $response->json('job.id'))->firstOrFail();
        $this->assertSame(TrackedJob::STATUS_COMPLETED, $job->status, $job->message ?? '');

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
        $this->assertDatabaseCount('tracked_jobs', 0);
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

        $response->assertStatus(Response::HTTP_ACCEPTED)
            ->assertJsonPath('job.type', 'media.delete');

        $job = TrackedJob::where('uuid', $response->json('job.id'))->firstOrFail();
        $this->assertSame(TrackedJob::STATUS_COMPLETED, $job->status, $job->message ?? '');

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
    // Download (queued)
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $config */
    #[DataProvider('mediaTypeProvider')]
    public function test_authorized_user_can_request_queued_download(array $config): void
    {
        $type = $config['type'];
        $disk = $this->diskFor($type);
        Storage::fake($disk);
        $this->loginAsRole('reader');
        $record = ($config['factory'])();
        // Make sure the file exists on the faked disk so PrepareMediaDownloadJob
        // doesn't bail with "File not available".
        Storage::disk($disk)->put($record->media->file_path, 'fake-pdf-bytes');

        $response = $this->postJson("/api/$type/{$record->getKey()}/download");

        $response->assertStatus(Response::HTTP_ACCEPTED)
            ->assertJsonPath('job.type', 'media.download.prepare');

        $job = TrackedJob::where('uuid', $response->json('job.id'))->firstOrFail();
        $this->assertSame(TrackedJob::STATUS_COMPLETED, $job->status, $job->message ?? '');
        $this->assertNotEmpty($job->result['url'] ?? null);
        $this->assertStringContainsString('signature=', $job->result['url']);
    }

    // ---------------------------------------------------------------------
    // Cross-cutting (no data provider — single request)
    // ---------------------------------------------------------------------

    public function test_unknown_media_type_is_rejected_by_router(): void
    {
        $this->loginAsRole('admin');

        $this->postJson('/api/widget', [])
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

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
