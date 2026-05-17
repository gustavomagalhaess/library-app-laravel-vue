<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Concerns;

use App\Domain\Jobs\Models\TrackedJob;
use Throwable;

/**
 * Shared lifecycle bookkeeping for every queued job that the SPA polls.
 *
 * Every job stores its TrackedJob primary key (`$trackedJobId`) and wraps its
 * real work inside `run()`. The trait flips the status to "processing" before
 * calling run(), to "completed" on success, and to "failed" if anything
 * leaks — including the Laravel queue's own `failed()` hook for fatal errors
 * such as max-attempts exhaustion.
 *
 * This guarantees the SPA's polling loop always sees a terminal state, which
 * is the only way the optimistic UI can know when to stop showing the
 * "Saving…" toast and reconcile its temporary row.
 */
trait TracksProgress
{
    public int $trackedJobId;

    /**
     * Concrete jobs implement run(). The framework still calls handle();
     * this trait wraps handle() around the status transitions.
     */
    abstract protected function run(TrackedJob $job): array;

    public function handle(): void
    {
        $job = TrackedJob::find($this->trackedJobId);
        if ($job === null) {
            // The tracking row was deleted between dispatch and execution.
            // Nothing useful we can do — bail silently rather than trying to
            // resurrect it. This is rare and only happens if an admin nukes
            // the table.
            return;
        }

        $job->markProcessing();

        try {
            $result = $this->run($job);
            $job->markCompleted($result);
        } catch (Throwable $e) {
            // Re-throw so Laravel can apply its retry policy. failed() below
            // will be invoked by the queue worker once attempts are exhausted
            // and will write the final "failed" terminal state.
            $job->markFailed($this->userFacingMessage($e));
            throw $e;
        }
    }

    /**
     * Called by the queue worker after the configured retry budget is
     * exhausted. By this point handle() has already marked the row failed,
     * but the row may still be in "processing" if the job aborted hard
     * (e.g. timeout) — make sure we leave the SPA's polling loop able to
     * resolve to a terminal state.
     */
    public function failed(Throwable $e): void
    {
        $job = TrackedJob::find($this->trackedJobId);
        if ($job !== null && !$job->isTerminal()) {
            $job->markFailed($this->userFacingMessage($e));
        }
    }

    /**
     * Strip framework noise from the message we show to end-users.
     * Anything past the first newline (typically the stack trace summary)
     * is dropped. We log the full exception in handle()'s wrapper so we
     * never lose debug context.
     */
    private function userFacingMessage(Throwable $e): string
    {
        $message = trim((string) $e->getMessage());
        if ($message === '') {
            return 'The operation failed. Please try again.';
        }
        $firstLine = strtok($message, "\n");
        return $firstLine !== false ? $firstLine : $message;
    }
}
