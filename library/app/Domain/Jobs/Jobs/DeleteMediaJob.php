<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Jobs;

use App\Domain\Jobs\Concerns\TracksProgress;
use App\Domain\Jobs\Models\TrackedJob;
use App\Domain\Media\Services\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued delete for any media subtype.
 *
 * The controller checks the policy + existence and dispatches; we run the
 * cascade (file unlink + subtype + media + media_authors) on the worker.
 */
class DeleteMediaJob implements ShouldQueue
{
    use Queueable;
    use TracksProgress;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $type,
        public string $recordUuid,
        int $trackedJobId,
    ) {
        $this->trackedJobId = $trackedJobId;
        $this->onQueue('media');
    }

    protected function run(TrackedJob $job): array
    {
        /** @var MediaService $service */
        $service = app(MediaService::class);

        $record = $service->find($this->type, $this->recordUuid);
        if ($record === null) {
            // Already gone — treat as success so the SPA's optimistic removal
            // sticks rather than flashing the row back into the list.
            return ['id' => $this->recordUuid, 'already_deleted' => true];
        }

        $service->delete($this->type, $record);

        return ['id' => $this->recordUuid];
    }
}
