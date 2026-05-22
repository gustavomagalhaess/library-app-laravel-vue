<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Jobs;

use App\Domain\Jobs\Concerns\TracksProgress;
use App\Domain\Jobs\Models\TrackedJob;
use App\Domain\Media\Services\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued create-or-update for any media subtype.
 *
 * The controller has already:
 *   1. Authorised the action via the policy.
 *   2. Validated the incoming request (FormRequest).
 *   3. Persisted the uploaded file (if any) to the type's disk.
 *   4. Created the TrackedJob row.
 *
 * This job picks up where the controller left off, runs the domain
 * persistence inside the worker, and writes the new/updated record back to
 * the TrackedJob's `result` column so the SPA's polling loop can reconcile
 * its optimistic row.
 */
class PersistMediaJob implements ShouldQueue
{
    use Queueable;
    use TracksProgress;

    /** @var int Retry budget — file IO + DB writes are usually idempotent under our schema. */
    public int $tries = 3;

    /** @var int Hard timeout per attempt (seconds). */
    public int $timeout = 60;

    /**
     * @param 'create'|'update'                 $operation
     * @param string                            $type              media morph alias (book, …)
     * @param int                               $trackedJobId      PK of the TrackedJob row to report into
     * @param array<string, mixed>              $attributes        shared + subtype-specific fields
     * @param array{ids?:int[], new?:string[]}  $authorsInput
     * @param int[]                             $classificationIds pre-seeded classification IDs to sync
     * @param string|null                       $storedFilePath    path on the type's disk; null on update with no new file
     * @param string|null                       $recordUuid        UUID of the existing record on update; null on create
     */
    public function __construct(
        public string $operation,
        public string $type,
        int $trackedJobId,
        public array $attributes,
        public array $authorsInput,
        public array $classificationIds = [],
        public ?string $storedFilePath = null,
        public ?string $recordUuid = null,
    ) {
        $this->trackedJobId = $trackedJobId;
        $this->onQueue('media');
    }

    protected function run(TrackedJob $job): array
    {
        /** @var MediaService $service */
        $service = app(MediaService::class);

        if ($this->operation === 'create') {
            // storedFilePath must be present on create — the controller's
            // FormRequest enforces the file rule.
            if ($this->storedFilePath === null) {
                throw new \RuntimeException('A file is required to create a media record.');
            }

            $record = $service->createFromStoredFile(
                type: $this->type,
                attributes: $this->attributes,
                authorsInput: $this->authorsInput,
                storedFilePath: $this->storedFilePath,
                classificationIds: $this->classificationIds,
            );
        } else {
            if ($this->recordUuid === null) {
                throw new \RuntimeException('A record UUID is required to update a media record.');
            }
            $record = $service->find($this->type, $this->recordUuid);
            if ($record === null) {
                throw new \RuntimeException('The media record no longer exists.');
            }

            $record = $service->updateFromStoredFile(
                type: $this->type,
                record: $record,
                attributes: $this->attributes,
                authorsInput: $this->authorsInput,
                storedFilePath: $this->storedFilePath,
                classificationIds: $this->classificationIds,
            );
        }

        // Eager-load relations the SPA's list rows expect, so the frontend can
        // splice the result straight into the existing table without a second
        // request.
        $record->loadMissing(['media.authors', 'media.classifications']);

        $job->forceFill(['resource_id' => $record->uuid ?? null])->save();

        return ['record' => $record->toArray()];
    }
}
