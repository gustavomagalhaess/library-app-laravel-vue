<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Jobs\Models\TrackedJob;
use App\Domain\Media\MediaTypeRegistry;
use App\Domain\Media\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints the SPA uses to track queued jobs.
 *
 *   GET /api/jobs/{uuid}                   → JSON status snapshot (polled)
 *   GET /jobs/media/{type}/{id}/download   → signed-URL streaming download
 *                                            (the URL is minted by
 *                                            PrepareMediaDownloadJob)
 *
 * Authorisation:
 *   - Status polling requires that the requesting user owns the job
 *     (TrackedJob.user_id matches). Admins also get to see anything.
 *   - Signed downloads are gated by the signature middleware — no auth check
 *     here because the URL itself is the capability.
 */
class JobsController extends Controller
{
    public function show(Request $request, string $uuid): JsonResponse
    {
        $job = TrackedJob::where('uuid', $uuid)->first();
        if ($job === null) {
            return response()->json(['message' => 'Job not found.'], Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();
        $isOwner = $user !== null && $job->user_id === $user->id;
        $isAdmin = $user !== null && $user->roles()->where('name', 'admin')->exists();

        if (! $isOwner && ! $isAdmin) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['job' => $job->toPublicArray()]);
    }

    /**
     * Serves the file backed by a TrackedJob's signed URL. The signature
     * middleware validates the URL hasn't expired or been tampered with;
     * we then stream from the type's disk.
     */
    public function download(string $type, string $id, string $job): StreamedResponse
    {
        /** @var MediaService $mediaService */
        $mediaService = app(MediaService::class);
        /** @var MediaTypeRegistry $registry */
        $registry = app(MediaTypeRegistry::class);

        $tracked = TrackedJob::where('uuid', $job)->first();
        if ($tracked === null || $tracked->status !== TrackedJob::STATUS_COMPLETED) {
            abort(404, 'Download is not ready.');
        }

        $record = $mediaService->find($type, $id);
        if ($record === null) {
            abort(404, 'File not available.');
        }

        $disk = $registry->for($type)->disk;
        $path = $record->media?->file_path;
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'File not available.');
        }

        $filename = sprintf(
            '%s.pdf',
            preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($record->media?->title ?? '')) ?: $type,
        );

        return Storage::disk($disk)->download($path, $filename);
    }
}
