<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
 * Every action takes the `{type}` URL segment and forwards it to the unified
 * {@see MediaService}. There is no per-type dispatch (no `serviceFor()`,
 * no `BookService`) — adding a media type only means adding a Model +
 * migration + a line in config/media.php.
 *
 * Reads (Inertia, declared in routes/web.php):
 *   GET  /{type}                  → list
 *   GET  /{type}/search           → JSON search
 *   GET  /{type}/{id}/download    → file download
 *
 * Mutations (JSON, declared in routes/api.php):
 *   POST   /api/{type}            → store
 *   POST   /api/{type}/{id}       → update (POST because file uploads can't ride PUT)
 *   DELETE /api/{type}/{id}       → destroy
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
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
            // The shared fields (title/publication_year/file) come from the
            // FormRequest; subtype-specific fields are pulled out by name and
            // validated inside the service against the rules declared on the
            // subtype model.
            $record = $this->mediaService->create(
                type: $type,
                attributes: array_merge(
                    $request->safe()->only(['title', 'publication_year']),
                    $request->only($this->mediaService->typeSpecificFields($type)),
                ),
                authorsInput: [
                    'ids' => $request->input('authors.ids', []),
                    'new' => $request->input('authors.new', []),
                ],
                file: $request->file('file'),
            );

            return response()->json(['data' => $record], Response::HTTP_CREATED);
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

            $updated = $this->mediaService->update(
                type: $type,
                record: $record,
                attributes: array_merge(
                    $request->safe()->only(['title', 'publication_year']),
                    $request->only($this->mediaService->typeSpecificFields($type)),
                ),
                authorsInput: [
                    'ids' => $request->input('authors.ids', []),
                    'new' => $request->input('authors.new', []),
                ],
                file: $request->file('file'),
            );

            return response()->json(['data' => $updated]);
        } catch (MediaException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
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

    public function destroy(string $type, string $id): JsonResponse
    {
        try {
            $record = $this->mediaService->find($type, $id);
            if ($record === null) {
                return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->mediaService->delete($type, $record);

            return response()->json(null, Response::HTTP_NO_CONTENT);
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
}
