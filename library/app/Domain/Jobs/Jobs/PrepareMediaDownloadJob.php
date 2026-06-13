<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Jobs;

use App\Domain\Jobs\Concerns\TracksProgress;
use App\Domain\Jobs\Models\TrackedJob;
use App\Domain\Media\MediaTypeRegistry;
use App\Domain\Media\Services\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Prepares a media file for download.
 *
 * Today the file is already on the local books disk, so "preparation" is
 * just: confirm existence, generate a short-lived signed URL the SPA can
 * follow once the toast resolves. The job exists as its own primitive so:
 *
 *   - Bulk / ZIP exports can slot in later without changing the UX contract.
 *   - Downloads from a remote disk (S3, Glacier restore, etc.) can be staged
 *     here without making the controller block on a slow third party.
 *   - Audit / metering happens uniformly in one place.
 *
 * The result payload is `{ url, filename, expires_at }` so the SPA's toast
 * can render "Download is ready — click here".
 */
class PrepareMediaDownloadJob implements ShouldQueue
{
    use Queueable;
    use TracksProgress;

    public int $tries = 2;

    public int $timeout = 120;

    /** Signed-URL lifetime — long enough for a human to click, short enough to limit replay. */
    private const URL_TTL_MINUTES = 10;

    public function __construct(
        public string $type,
        public string $recordUuid,
        int $trackedJobId,
    ) {
        $this->trackedJobId = $trackedJobId;
        $this->onQueue('downloads');
    }

    protected function run(TrackedJob $job): array
    {
        /** @var MediaService $mediaService */
        $mediaService = app(MediaService::class);
        /** @var MediaTypeRegistry $registry */
        $registry = app(MediaTypeRegistry::class);

        $record = $mediaService->find($this->type, $this->recordUuid);
        if ($record === null) {
            throw new \RuntimeException('The requested item is no longer available.');
        }

        $definition = $registry->for($this->type);
        $path = $record->media?->file_path;
        if (! $path || ! Storage::disk($definition->disk)->exists($path)) {
            throw new \RuntimeException('File not available for download.');
        }

        $filename = sprintf(
            '%s.pdf',
            preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($record->media?->title ?? '')) ?: $this->type,
        );

        // Signed URL into the dedicated streaming controller. The controller
        // re-validates the type + id at fetch time, so this URL is safe to
        // hand out across the wire.
        $url = URL::temporarySignedRoute(
            name: 'jobs.media.download',
            expiration: now()->addMinutes(self::URL_TTL_MINUTES),
            parameters: [
                'type' => $this->type,
                'id' => $this->recordUuid,
                'job' => $job->uuid,
            ],
        );

        return [
            'url' => $url,
            'filename' => $filename,
            'expires_at' => now()->addMinutes(self::URL_TTL_MINUTES)->toIso8601String(),
        ];
    }
}
