<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Domain\Jobs\Jobs\DeleteAuthorJob;
use App\Domain\Jobs\Jobs\DeleteMediaJob;
use App\Domain\Jobs\Jobs\PersistAuthorJob;
use App\Domain\Jobs\Jobs\PersistMediaJob;
use App\Domain\Jobs\Jobs\PrepareMediaDownloadJob;
use App\Domain\Jobs\Models\TrackedJob;
use App\Domain\Author\Models\Author;
use App\Models\User;
use Database\Factories\BookFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Tests the queued-jobs orchestration end of the stack:
 *   - Controllers create a TrackedJob row and dispatch the right job class
 *     to the right queue.
 *   - The /api/jobs/{uuid} status endpoint is authz-scoped to the job's
 *     owner (with an admin override) and returns the public projection.
 */
class JobsApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Dispatch wiring
    // ---------------------------------------------------------------------

    public function test_media_create_dispatches_persist_media_job_on_media_queue(): void
    {
        Bus::fake();
        Storage::fake('books');
        $this->loginAsRole('admin');
        $author = Author::factory()->create();

        $this->postJson('/api/book', [
            'title'            => 'Queued Title',
            'publication_year' => 2024,
            'pages'            => 100,
            'file'             => UploadedFile::fake()->create('book.pdf', 100, 'application/pdf'),
            'authors'          => ['ids' => [$author->id], 'new' => []],
        ])->assertStatus(Response::HTTP_ACCEPTED);

        Bus::assertDispatched(PersistMediaJob::class, function (PersistMediaJob $job): bool {
            return $job->operation === 'create'
                && $job->type === 'book'
                && $job->queue === 'media';
        });

        $this->assertDatabaseHas('tracked_jobs', [
            'type'   => 'media.create',
            'status' => TrackedJob::STATUS_QUEUED,
        ]);
    }

    public function test_media_delete_dispatches_delete_media_job(): void
    {
        Bus::fake();
        Storage::fake('books');
        $this->loginAsRole('admin');
        $record = BookFactory::new()->create();

        $this->deleteJson("/api/book/{$record->getKey()}")
            ->assertStatus(Response::HTTP_ACCEPTED);

        Bus::assertDispatched(
            DeleteMediaJob::class,
            fn (DeleteMediaJob $job) => $job->recordUuid === $record->getKey() && $job->queue === 'media',
        );
    }

    public function test_author_create_dispatches_persist_author_job_on_authors_queue(): void
    {
        Bus::fake();
        $this->loginAsRole('admin');

        $this->postJson('/api/authors', ['name' => 'Ursula K. Le Guin'])
            ->assertStatus(Response::HTTP_ACCEPTED);

        Bus::assertDispatched(PersistAuthorJob::class, function (PersistAuthorJob $job): bool {
            return $job->operation === 'create' && $job->queue === 'authors';
        });
    }

    public function test_author_delete_dispatches_delete_author_job(): void
    {
        Bus::fake();
        $this->loginAsRole('admin');
        $author = Author::factory()->create();

        $this->deleteJson("/api/authors/{$author->id}")
            ->assertStatus(Response::HTTP_ACCEPTED);

        Bus::assertDispatched(
            DeleteAuthorJob::class,
            fn (DeleteAuthorJob $job) => $job->authorId === $author->id && $job->queue === 'authors',
        );
    }

    public function test_download_dispatches_prepare_download_job_on_downloads_queue(): void
    {
        Bus::fake();
        Storage::fake('books');
        $this->loginAsRole('reader');
        $record = BookFactory::new()->create();

        $this->postJson("/api/book/{$record->getKey()}/download")
            ->assertStatus(Response::HTTP_ACCEPTED);

        Bus::assertDispatched(
            PrepareMediaDownloadJob::class,
            fn (PrepareMediaDownloadJob $job) => $job->recordUuid === $record->getKey() && $job->queue === 'downloads',
        );
    }

    // ---------------------------------------------------------------------
    // GET /api/jobs/{uuid}
    // ---------------------------------------------------------------------

    public function test_owner_can_read_their_job_status(): void
    {
        $user = $this->loginAsRole('admin');
        $job = TrackedJob::create([
            'user_id' => $user->id,
            'type'    => 'media.create',
            'status'  => TrackedJob::STATUS_COMPLETED,
            'result'  => ['record' => ['id' => 7]],
        ]);

        $this->getJson("/api/jobs/{$job->uuid}")
            ->assertOk()
            ->assertJsonPath('job.id', $job->uuid)
            ->assertJsonPath('job.status', TrackedJob::STATUS_COMPLETED);
    }

    public function test_other_users_cannot_read_someone_elses_job(): void
    {
        $owner = User::factory()->create();
        $job = TrackedJob::create([
            'user_id' => $owner->id,
            'type'    => 'media.create',
            'status'  => TrackedJob::STATUS_QUEUED,
        ]);

        $this->loginAsRole('reader');

        $this->getJson("/api/jobs/{$job->uuid}")
            ->assertForbidden();
    }

    public function test_admin_can_read_any_users_job_status(): void
    {
        $owner = User::factory()->create();
        $job = TrackedJob::create([
            'user_id' => $owner->id,
            'type'    => 'media.create',
            'status'  => TrackedJob::STATUS_QUEUED,
        ]);

        $this->loginAsRole('admin');

        $this->getJson("/api/jobs/{$job->uuid}")
            ->assertOk()
            ->assertJsonPath('job.id', $job->uuid);
    }

    public function test_unknown_job_uuid_returns_404(): void
    {
        $this->loginAsRole('admin');

        $this->getJson('/api/jobs/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    private function loginAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
