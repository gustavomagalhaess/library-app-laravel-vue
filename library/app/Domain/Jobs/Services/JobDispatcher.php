<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Services;

use App\Domain\Jobs\Jobs\DeleteAuthorJob;
use App\Domain\Jobs\Jobs\DeleteMediaJob;
use App\Domain\Jobs\Jobs\PersistAuthorJob;
use App\Domain\Jobs\Jobs\PersistMediaJob;
use App\Domain\Jobs\Jobs\PrepareMediaDownloadJob;
use App\Domain\Jobs\Models\TrackedJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

/**
 * One-stop shop for "create a TrackedJob row, dispatch the matching queued
 * job". Centralises the bookkeeping so controllers stay thin and the
 * TrackedJob ↔ Job class mapping lives in exactly one place.
 *
 * Every method returns the TrackedJob so the controller can echo
 * `toPublicArray()` back to the SPA along with the 202 response.
 */
final class JobDispatcher
{
    /**
     * @param array<string, mixed>            $attributes
     * @param array{ids?:int[], new?:string[]} $authorsInput
     */
    public function dispatchMediaCreate(
        ?User $user,
        string $type,
        array $attributes,
        array $authorsInput,
        string $storedFilePath,
    ): TrackedJob {
        $job = $this->trackingRow($user, 'media.create', payload: [
            'type' => $type,
            'title' => $attributes['title'] ?? null,
        ]);

        Bus::dispatch(new PersistMediaJob(
            operation: 'create',
            type: $type,
            trackedJobId: $job->id,
            attributes: $attributes,
            authorsInput: $authorsInput,
            storedFilePath: $storedFilePath,
            recordUuid: null,
        ));

        return $job;
    }

    /**
     * @param array<string, mixed>            $attributes
     * @param array{ids?:int[], new?:string[]} $authorsInput
     */
    public function dispatchMediaUpdate(
        ?User $user,
        string $type,
        string $recordUuid,
        array $attributes,
        array $authorsInput,
        ?string $storedFilePath,
    ): TrackedJob {
        $job = $this->trackingRow(
            user: $user,
            type: 'media.update',
            payload: ['type' => $type, 'title' => $attributes['title'] ?? null],
            resourceId: $recordUuid,
        );

        Bus::dispatch(new PersistMediaJob(
            operation: 'update',
            type: $type,
            trackedJobId: $job->id,
            attributes: $attributes,
            authorsInput: $authorsInput,
            storedFilePath: $storedFilePath,
            recordUuid: $recordUuid,
        ));

        return $job;
    }

    public function dispatchMediaDelete(?User $user, string $type, string $recordUuid): TrackedJob
    {
        $job = $this->trackingRow(
            user: $user,
            type: 'media.delete',
            payload: ['type' => $type],
            resourceId: $recordUuid,
        );

        Bus::dispatch(new DeleteMediaJob(
            type: $type,
            recordUuid: $recordUuid,
            trackedJobId: $job->id,
        ));

        return $job;
    }

    public function dispatchAuthorCreate(?User $user, string $name): TrackedJob
    {
        $job = $this->trackingRow($user, 'author.create', payload: ['name' => $name]);

        Bus::dispatch(new PersistAuthorJob(
            operation: 'create',
            name: $name,
            trackedJobId: $job->id,
            authorId: null,
        ));

        return $job;
    }

    public function dispatchAuthorUpdate(?User $user, int $authorId, string $name): TrackedJob
    {
        $job = $this->trackingRow(
            user: $user,
            type: 'author.update',
            payload: ['name' => $name],
            resourceId: (string) $authorId,
        );

        Bus::dispatch(new PersistAuthorJob(
            operation: 'update',
            name: $name,
            trackedJobId: $job->id,
            authorId: $authorId,
        ));

        return $job;
    }

    public function dispatchAuthorDelete(?User $user, int $authorId): TrackedJob
    {
        $job = $this->trackingRow(
            user: $user,
            type: 'author.delete',
            payload: [],
            resourceId: (string) $authorId,
        );

        Bus::dispatch(new DeleteAuthorJob(
            authorId: $authorId,
            trackedJobId: $job->id,
        ));

        return $job;
    }

    public function dispatchMediaDownload(?User $user, string $type, string $recordUuid): TrackedJob
    {
        $job = $this->trackingRow(
            user: $user,
            type: 'media.download.prepare',
            payload: ['type' => $type],
            resourceId: $recordUuid,
        );

        Bus::dispatch(new PrepareMediaDownloadJob(
            type: $type,
            recordUuid: $recordUuid,
            trackedJobId: $job->id,
        ));

        return $job;
    }

    /** @param array<string, mixed> $payload */
    private function trackingRow(?User $user, string $type, array $payload, ?string $resourceId = null): TrackedJob
    {
        return TrackedJob::create([
            'user_id'     => $user?->id,
            'type'        => $type,
            'resource_id' => $resourceId,
            'status'      => TrackedJob::STATUS_QUEUED,
            'payload'     => $payload,
        ]);
    }
}
