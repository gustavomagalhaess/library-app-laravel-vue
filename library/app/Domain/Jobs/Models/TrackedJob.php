<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One row per dispatched user-visible job.
 *
 * The DB primary key remains `id` because Eloquent relations + queue
 * payload references are happier that way; we expose the public `uuid`
 * to the SPA so internal IDs never leak.
 *
 * Lifecycle:
 *   queued   → created when the controller dispatches the job
 *   processing → set by the job's handle() before doing real work
 *   completed  → the job persisted successfully; `result` holds whatever
 *                the SPA needs (the new/updated record, a signed
 *                download URL, …)
 *   failed     → the job threw; `message` carries the user-facing reason.
 *
 * @property string $uuid
 * @property string $type
 * @property string $status
 * @property ?string $resource_id
 * @property ?string $message
 * @property ?array $payload
 * @property ?array $result
 */
class TrackedJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'tracked_jobs';

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'resource_id',
        'status',
        'message',
        'payload',
        'result',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(static function (TrackedJob $job): void {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
            if (empty($job->status)) {
                $job->status = self::STATUS_QUEUED;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ---------------------------------------------------------------------
    // Lifecycle helpers — keep the status transitions in one place so jobs
    // don't accidentally skip steps (e.g. completing without ever moving
    // out of "queued", which would confuse the optimistic UI on the SPA).
    // ---------------------------------------------------------------------

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'started_at' => Carbon::now(),
        ])->save();
    }

    public function markCompleted(array $result = [], ?string $message = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_COMPLETED,
            'message' => $message,
            'result' => $result,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    public function markFailed(string $message, array $result = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'message' => $message,
            'result' => $result,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /**
     * Shape returned to the SPA — intentionally narrow so we don't leak
     * internal columns or full payloads.
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     status: string,
     *     resource_id: ?string,
     *     message: ?string,
     *     result: ?array,
     *     started_at: ?string,
     *     finished_at: ?string,
     * }
     */
    public function toPublicArray(): array
    {
        return [
            'id'           => $this->uuid,
            'type'         => $this->type,
            'status'       => $this->status,
            'resource_id'  => $this->resource_id,
            'message'      => $this->message,
            'result'       => $this->result,
            'started_at'   => optional($this->started_at)->toIso8601String(),
            'finished_at'  => optional($this->finished_at)->toIso8601String(),
        ];
    }
}
