<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Jobs\Services\JobDispatcher;
use App\Domain\Media\Exceptions\MediaException;
use App\Domain\Media\Messages\MediaMessage;
use App\Domain\Media\Services\MediaService;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Front controller for every media type the application supports.
 *
 * Mutations are now queued — the controller's job is to authorise + validate
 * + persist the uploaded file (UploadedFile is not queue-serialisable), then
 * dispatch a job and return 202 with a TrackedJob descriptor. The SPA polls
 * `/api/jobs/{id}` to learn when the row is real.
 *
 * Reads (Inertia, declared in routes/web.php):
 *   GET  /{type}                  → list
 *   GET  /{type}/search           → JSON search
 *   GET  /{type}/{id}/download    → file download (sync; legacy fallback)
 *
 * Mutations (JSON, declared in routes/api.php):
 *   POST   /api/{type}            → queue store        → 202 + TrackedJob
 *   POST   /api/{type}/{id}       → queue update       → 202 + TrackedJob
 *   DELETE /api/{type}/{id}       → queue destroy      → 202 + TrackedJob
 *   POST   /api/{type}/{id}/download → queue prepare   → 202 + TrackedJob
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly JobDispatcher $jobDispatcher,
    ) {}

    public function index(Request $request, string $type): InertiaResponse
    {
        try {
            $query = $this->mediaService->validatedSearchTerm(trim((string) $request->query('q', '')));
            $page = $this->mediaService->pageFor($type, 'index');
            $mediaType = Str::plural(Str::lower($type));

            return Inertia::render($page, [
                $mediaType => $this->mediaService->list($type, $query),
                'filters' => ['q' => $query],
                'can' => $this->mediaService->permissionsFor($request->user(), $type),
                'type' => $type,
            ]);
        } catch (MediaException $e) {
            abort(404, $e->getMessage());
        } catch (\Throwable $e) {
            app('log')->error($e->getMessage(), ['exception' => $e]);
            abort(404, MediaMessage::ERROR);
        }
    }

    public function search(Request $request, string $type): JsonResponse
    {
        try {
            $query = $this->mediaService->validatedSearchTerm(trim((string) $request->query('q', '')));

            return response()->json($this->mediaService->list($type, $query));
        } catch (MediaException $e) {
            abort(404, $e->getMessage());
        } catch (\Throwable $e) {
            app('log')->error($e->getMessage(), ['exception' => $e]);
            abort(404, MediaMessage::ERROR);
        }
    }

    public function store(StoreMediaRequest $request, string $type): JsonResponse
    {
        try {
            $attributes = array_merge(
                $request->safe()->only(['title', 'publication_year']),
                $request->only($this->mediaService->typeSpecificFields($type)),
            );

            // The upload is small + local, so storing it synchronously here
            // is fine and keeps UploadedFile out of the Redis payload.
            $storedPath = $this->mediaService->storeFile($type, $request->file('file'));

            $job = $this->jobDispatcher->dispatchMediaCreate(
                user: $request->user(),
                type: $type,
                attributes: $attributes,
                authorsInput: [
                    'ids' => $request->input('authors.ids', []),
                    'new' => $request->input('authors.new', []),
                ],
                storedFilePath: $storedPath,
                classificationIds: array_map('intval', $request->input('classifications.ids', [])),
            );

            return response()->json(['job' => $job->toPublicArray()], Response::HTTP_ACCEPTED);
        } catch (MediaException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            app('log')->error(
                sprintf(MediaMessage::FAILED_TO_PERSIST_MEDIA_LOG, 'create', $type, $e->getMessage()),
                ['exception' => $e]
            );

            return response()->json(
                ['message' => sprintf(MediaMessage::FAILED_TO_PERSIST_MEDIA, 'create', $type)],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function update(UpdateMediaRequest $request, string $type, string $id): JsonResponse
    {
        try {
            $record = $this->mediaService->find($type, $id);
            if ($record === null) {
                return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
            }

            $attributes = array_merge(
                $request->safe()->only(['title', 'publication_year']),
                $request->only($this->mediaService->typeSpecificFields($type)),
            );

            $storedPath = $request->hasFile('file')
                ? $this->mediaService->storeFile($type, $request->file('file'))
                : null;

            $job = $this->jobDispatcher->dispatchMediaUpdate(
                user: $request->user(),
                type: $type,
                recordUuid: $id,
                attributes: $attributes,
                authorsInput: [
                    'ids' => $request->input('authors.ids', []),
                    'new' => $request->input('authors.new', []),
                ],
                storedFilePath: $storedPath,
                classificationIds: array_map('intval', $request->input('classifications.ids', [])),
            );

            return response()->json(['job' => $job->toPublicArray()], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            app('log')->error(
                sprintf(MediaMessage::FAILED_TO_PERSIST_MEDIA_LOG, 'update', $type, $e->getMessage()),
                ['exception' => $e]
            );

            return response()->json(
                ['message' => sprintf(MediaMessage::FAILED_TO_PERSIST_MEDIA, 'update', $type)],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function destroy(Request $request, string $type, string $id): JsonResponse
    {
        try {
            $record = $this->mediaService->find($type, $id);
            if ($record === null) {
                return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
            }

            $job = $this->jobDispatcher->dispatchMediaDelete(
                user: $request->user(),
                type: $type,
                recordUuid: $id,
            );

            return response()->json(['job' => $job->toPublicArray()], Response::HTTP_ACCEPTED);
        } catch (MediaException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            app('log')->error(
                sprintf(MediaMessage::FAILED_TO_PERSIST_MEDIA_LOG, 'delete', $type, $e->getMessage()),
                ['exception' => $e]
            );

            return response()->json(
                ['message' => sprintf(MediaMessage::FAILED_TO_PERSIST_MEDIA, 'delete', $type)],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Sync download (kept for direct-link compatibility and as a fallback in
     * case the queued worker is unavailable). The SPA prefers the
     * `requestDownload()` API endpoint below.
     */
    public function download(string $type, string $id): StreamedResponse
    {
        try {
            return $this->mediaService->download($type, $id);
        } catch (MediaException $e) {
            abort(404, $e->getMessage());
        } catch (\Throwable $e) {
            app('log')->error($e->getMessage(), ['exception' => $e]);
            abort(404, MediaMessage::ERROR);
        }
    }

    /**
     * Queue a prepared download. Returns 202 + TrackedJob; once the SPA's
     * polling resolves, it follows `result.url` to actually fetch the file.
     */
    public function requestDownload(Request $request, string $type, string $id): JsonResponse
    {
        try {
            $record = $this->mediaService->find($type, $id);
            if ($record === null) {
                return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
            }

            $job = $this->jobDispatcher->dispatchMediaDownload(
                user: $request->user(),
                type: $type,
                recordUuid: $id,
            );

            return response()->json(['job' => $job->toPublicArray()], Response::HTTP_ACCEPTED);
        } catch (MediaException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            app('log')->error($e->getMessage(), ['exception' => $e]);

            return response()->json(
                ['message' => MediaMessage::ERROR],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
