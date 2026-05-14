<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Author\Exceptions\AuthorHasBooksException;
use App\Domain\Author\Models\Author;
use App\Domain\Author\Services\AuthorService;
use App\Http\Requests\Author\StoreAuthorRequest;
use App\Http\Requests\Author\UpdateAuthorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Reads (Inertia, declared in routes/web.php):
 *   GET /authors          → list
 *   GET /authors/search   → JSON search (used by the BookForm typeahead)
 *
 * Mutations (JSON, declared in routes/api.php):
 *   POST   /api/authors            → store
 *   PUT    /api/authors/{author}   → update
 *   DELETE /api/authors/{author}   → destroy
 */
class AuthorController extends Controller
{
    public function __construct(
        private readonly AuthorService $authorService,
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
            $author = $this->authorService->create($request->string('name')->trim()->toString());

            return response()->json(['data' => $author], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            app('log')->error('Failed to create author: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(
                ['message' => 'Failed to create author. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function update(UpdateAuthorRequest $request, Author $author): JsonResponse
    {
        try {
            $updated = $this->authorService->update($author, $request->string('name')->trim()->toString());

            return response()->json(['data' => $updated]);
        } catch (\Throwable $e) {
            app('log')->error('Failed to update author: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(
                ['message' => 'Failed to update author. Please try again.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function destroy(Author $author): JsonResponse
    {
        try {
            $this->authorService->delete($author);

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (AuthorHasBooksException $e) {
            // 409 — the request was valid but the current resource state
            // (this author is still linked to books) prevents deletion.
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $e) {
            app('log')->error('Failed to delete author: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(
                ['message' => 'Failed to delete author. Please try again.'],
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
