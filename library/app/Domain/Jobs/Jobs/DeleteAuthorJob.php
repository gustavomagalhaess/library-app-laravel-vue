<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Jobs;

use App\Domain\Author\Exceptions\AuthorHasBooksException;
use App\Domain\Author\Models\Author;
use App\Domain\Author\Services\AuthorService;
use App\Domain\Jobs\Concerns\TracksProgress;
use App\Domain\Jobs\Models\TrackedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued delete for an Author.
 *
 * The "still has books" constraint is enforced inside AuthorService; if it
 * trips we mark the job failed with the user-facing message so the SPA can
 * surface it. Re-tries don't help in that case, so tries = 1.
 */
class DeleteAuthorJob implements ShouldQueue
{
    use Queueable;
    use TracksProgress;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        public int $authorId,
        int $trackedJobId,
    ) {
        $this->trackedJobId = $trackedJobId;
        $this->onQueue('authors');
    }

    protected function run(TrackedJob $job): array
    {
        /** @var AuthorService $service */
        $service = app(AuthorService::class);

        $author = Author::find($this->authorId);
        if ($author === null) {
            return ['id' => $this->authorId, 'already_deleted' => true];
        }

        try {
            $service->delete($author);
        } catch (AuthorHasBooksException $e) {
            // Surface the precondition failure as a user-facing reason
            // rather than a generic 500-equivalent.
            throw new \RuntimeException($e->getMessage());
        }

        return ['id' => $this->authorId];
    }
}
