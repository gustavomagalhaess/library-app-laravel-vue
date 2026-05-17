<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Jobs;

use App\Domain\Author\Models\Author;
use App\Domain\Author\Services\AuthorService;
use App\Domain\Jobs\Concerns\TracksProgress;
use App\Domain\Jobs\Models\TrackedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued create-or-update for an Author.
 *
 * Authors are inexpensive (a single DB row, no files), but we route them
 * through the same job pipeline so the SPA sees uniform "queued → completed"
 * UX for every write — and so future heavyweight side-effects (search index,
 * cache warming, etc.) can be added without touching the controller.
 */
class PersistAuthorJob implements ShouldQueue
{
    use Queueable;
    use TracksProgress;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * @param 'create'|'update' $operation
     * @param int|null          $authorId   PK of the existing author on update; null on create
     */
    public function __construct(
        public string $operation,
        public string $name,
        int $trackedJobId,
        public ?int $authorId = null,
    ) {
        $this->trackedJobId = $trackedJobId;
        $this->onQueue('authors');
    }

    protected function run(TrackedJob $job): array
    {
        /** @var AuthorService $service */
        $service = app(AuthorService::class);

        if ($this->operation === 'create') {
            $author = $service->create($this->name);
        } else {
            if ($this->authorId === null) {
                throw new \RuntimeException('An author ID is required to update an author.');
            }
            $existing = Author::find($this->authorId);
            if ($existing === null) {
                throw new \RuntimeException('The author no longer exists.');
            }
            $author = $service->update($existing, $this->name);
        }

        $job->forceFill(['resource_id' => (string) $author->id])->save();

        return ['record' => $author->toArray()];
    }
}
