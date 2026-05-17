import axios from 'axios';

/**
 * Poll the job-status endpoint until the job reaches a terminal state
 * (completed | failed) and then resolve with the final TrackedJob snapshot.
 *
 *   const job = await trackJob(initialJob, { intervalMs: 1500 });
 *   if (job.status === 'completed') { … }
 *
 * Notes:
 *   - `initialJob` is the object returned by the controller's 202 response
 *     (`{ id, status, type, resource_id, … }`). If it's already terminal,
 *     we resolve immediately without polling.
 *   - The polling interval ramps up gently — DB/file IO is fast, so first
 *     few polls are tight (500ms) and longer jobs back off to 2s. This is
 *     much friendlier to the SPA than a fixed interval.
 *   - The hook never throws on a failed job; instead it resolves with the
 *     final snapshot and lets the caller inspect `status === 'failed'`.
 *     Network errors *do* reject so the caller can surface them.
 */
const DEFAULT_INTERVAL_MS = 750;
const MAX_INTERVAL_MS = 2500;
const DEFAULT_TIMEOUT_MS = 60_000;
const TERMINAL = new Set(['completed', 'failed']);

export function trackJob(initialJob, opts = {}) {
  const intervalMs = opts.intervalMs ?? DEFAULT_INTERVAL_MS;
  const timeoutMs = opts.timeoutMs ?? DEFAULT_TIMEOUT_MS;

  if (!initialJob?.id) {
    return Promise.reject(new Error('useJobTracker: missing job id'));
  }
  if (TERMINAL.has(initialJob.status)) {
    return Promise.resolve(initialJob);
  }

  const startedAt = Date.now();
  let currentInterval = intervalMs;

  return new Promise((resolve, reject) => {
    const tick = async () => {
      if (Date.now() - startedAt > timeoutMs) {
        reject(new Error('Job tracking timed out.'));
        return;
      }
      try {
        const { data } = await axios.get(route('api.jobs.show', { uuid: initialJob.id }));
        const job = data?.job;
        if (job && TERMINAL.has(job.status)) {
          resolve(job);
          return;
        }
        // Ramp the interval up — we don't want to hammer the server for a
        // job that's clearly taking longer than typical.
        currentInterval = Math.min(Math.round(currentInterval * 1.25), MAX_INTERVAL_MS);
        setTimeout(tick, currentInterval);
      } catch (err) {
        reject(err);
      }
    };
    // First poll after the initial interval (the worker has had no time yet
    // if we hit it immediately).
    setTimeout(tick, currentInterval);
  });
}
