<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Author\Models\Author;
use App\Domain\Author\Services\AuthorService;
use App\Domain\Jobs\Services\JobDispatcher;
use App\Http\Requests\Author\StoreAuthorRequest;
use App\Http\Requests\Author\UpdateAuthorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Reads stay synchronous; writes are queued.
 *
 * Reads (Inertia, declared in routes/web.php):
 *   GET /authors          → list
 *   GET /authors/search   → JSON search (used by the BookForm typeahead)
 *
 * Mutations (queued; declared in routes/api.php):
 *   POST   /api/authors            → store    → 202 + TrackedJob
 *   PUT    /api/authors/{author}   → update   → 202 + TrackedJob
 *   DELETE /api/authors/{author}   → destroy  → 202 + TrackedJob
 */
class AuthorController extends Controller
{
    public function __construct(
        private readonly AuthorService $authorService,
        private readonly JobDispatcher $jobDispatcher,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $query = $this->validatedSearchTerm($request);

        return Inertia::render('Authors/Index', [
            'authors' => $this->authorService->list($query),
            'filters' => ['q' => $query],
            'can' => [
                'create' => $request->user()?->can('authors.create') ?? false,
                'update' => $request->user()?->can('authors.update') ?? false,
                'delete' => $request->user()?->can('authors.delete') ?? false,
            ],
        ]);
    }

    /**
     * Auto-complete endpoint used by the BookForm dropdown.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 3) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->authorService->search($query)]);
    }

    public function store(StoreAuthorRequest $request): JsonResponse
    {
        try {
            $job = $this->jobDispatcher->dispatchAuthorCreate(
                user: $request->user(),
                name: $request->string('name')->trim()->toString(),
            );

            return response()->json(['job' => $job->toPublicArray()], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            app('log')->error('Failed to queue author create: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(
                ['message' => 'Failed to queue author create. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function update(UpdateAuthorRequest $request, Author $author): JsonResponse
    {
        try {
            $job = $this->jobDispatcher->dispatchAuthorUpdate(
                user: $request->user(),
                authorId: $author->id,
                name: $request->string('name')->trim()->toString(),
            );

            return response()->json(['job' => $job->toPublicArray()], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            app('log')->error('Failed to queue author update: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(
                ['message' => 'Failed to queue author update. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function destroy(Request $request, Author $author): JsonResponse
    {
        try {
            $job = $this->jobDispatcher->dispatchAuthorDelete(
                user: $request->user(),
                authorId: $author->id,
            );

            return response()->json(['job' => $job->toPublicArray()], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            app('log')->error('Failed to queue author delete: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(
                ['message' => 'Failed to queue author delete. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    private function validatedSearchTerm(Request $request): ?string
    {
        $term = trim((string) $request->query('q', ''));

        return mb_strlen($term) >= 3 ? $term : null;
    }
}
