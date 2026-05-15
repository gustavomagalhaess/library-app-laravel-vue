<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Author\Models\Author;
use App\Domain\Author\Repositories\AuthorRepositoryInterface;
use App\Domain\Media\Exceptions\MediaException;
use App\Domain\Media\MediaTypeRegistry;
use App\Domain\Media\Messages\MediaMessage;
use App\Domain\Media\Repositories\MediaRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The single application service for the polymorphic Media front controller.
 *
 * This service replaces the per-type BookService / FutureMovieService / …
 * pattern: every method takes a `$type` morph alias (e.g. 'book') and the
 * {@see MediaTypeRegistry} resolves the right model class, storage disk and
 * validation rules. Adding a new media type therefore requires nothing more
 * than a Model + migration + a line in config/media.php — no new service
 * class is needed.
 *
 * Responsibilities, in order:
 *   - Resolve type-specific metadata via the registry.
 *   - Validate the subtype-only fields the FormRequest can't know about.
 *   - Drive the {@see MediaRepositoryInterface} for persistence.
 *   - Manage the uploaded PDF lifecycle on the per-type disk.
 *   - Provide tiny cross-cutting helpers used by the controller (Inertia page
 *     name, `can` payload, search-term sanitiser, file download).
 */
final readonly class MediaService
{
    public function __construct(
        private MediaRepositoryInterface  $mediaRepository,
        private AuthorRepositoryInterface $authorRepository,
        private MediaTypeRegistry         $registry,
    ) {}

    // ---------------------------------------------------------------------
    // Cross-cutting helpers (used by the controller / FormRequests / Inertia)
    // ---------------------------------------------------------------------

    /** Returns the trimmed search term iff it meets the 3-char minimum. */
    public function validatedSearchTerm(string $term): ?string
    {
        return Str::length($term) >= 3 ? $term : null;
    }

    /**
     * @return string[] Names of the subtype-only columns for `$type`.
     */
    public function typeSpecificFields(string $type): array
    {
        return $this->registry->for($type)->specificFields;
    }

    /**
     * Per-action booleans for the Inertia `can` payload (used by Vue to
     * show/hide action buttons). Delegates to the `media.*` Gates registered
     * in {@see \App\Providers\AuthServiceProvider} so the type → permission
     * mapping lives in exactly one place ({@see \App\Policies\MediaPolicy}).
     *
     * @return array{create:bool, update:bool, delete:bool, download:bool}
     */
    public function permissionsFor(?User $user, string $type): array
    {
        if ($user === null) {
            return ['create' => false, 'update' => false, 'delete' => false, 'download' => false];
        }

        return [
            'create'   => $user->can('media.create', $type),
            'update'   => $user->can('media.update', $type),
            'delete'   => $user->can('media.delete', $type),
            'download' => $user->can('media.download', $type),
        ];
    }

    /** Inertia page identifier for `$type` (e.g. ('book','index') → 'Books/Index'). */
    public function pageFor(string $type, string $page): string
    {
        $mediaType = Str::ucfirst(Str::plural(Str::lower($type)));
        $pageName = Str::ucfirst($page);

        return "$mediaType/$pageName";
    }

    // ---------------------------------------------------------------------
    // CRUD (parametrised by $type)
    // ---------------------------------------------------------------------

    public function count(string $type): int
    {
        return $this->mediaRepository->count($type);
    }

    /**
     * @return LengthAwarePaginator<Model>
     */
    public function list(string $type, ?string $query = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->mediaRepository->paginate($type, $query, $perPage);
    }

    public function find(string $type, string $uuid): ?Model
    {
        return $this->mediaRepository->find($type, $uuid);
    }

    /**
     * @param  array<string, mixed>             $attributes   Mixed payload — shared (title/year) + subtype-specific.
     * @param  array{ids?:int[], new?:string[]} $authorsInput
     */
    public function create(string $type, array $attributes, array $authorsInput, UploadedFile $file): Model
    {
        $subtypeAttributes = $this->validateSubtypeFields($type, $attributes);
        $authorIds = $this->resolveAuthorIds($authorsInput);
        $path = $this->storeFile($type, $file);

        return $this->mediaRepository->create(
            type: $type,
            subtypeAttributes: $subtypeAttributes,
            mediaAttributes: [
                'title'            => $attributes['title'],
                'publication_year' => $attributes['publication_year'],
                'file_path'        => $path,
            ],
            authorIds: $authorIds,
        );
    }

    /**
     * @param  array<string, mixed>                  $attributes
     * @param  array{ids?:int[], new?:string[]}|null $authorsInput  Pass null to leave authors untouched.
     */
    public function update(
        string $type,
        Model $record,
        array $attributes,
        ?array $authorsInput,
        ?UploadedFile $file,
    ): Model {
        $subtypeAttributes = $this->validateSubtypeFields($type, $attributes);

        $mediaAttributes = array_filter(
            [
                'title'            => $attributes['title'] ?? null,
                'publication_year' => $attributes['publication_year'] ?? null,
            ],
            static fn ($v) => $v !== null,
        );

        if ($file instanceof UploadedFile) {
            $newPath = $this->storeFile($type, $file);
            $disk = $this->registry->for($type)->disk;
            $previous = $record->media?->file_path;
            if ($previous && Storage::disk($disk)->exists($previous)) {
                Storage::disk($disk)->delete($previous);
            }
            $mediaAttributes['file_path'] = $newPath;
        }

        $authorIds = $authorsInput !== null ? $this->resolveAuthorIds($authorsInput) : null;

        return $this->mediaRepository->update($type, $record, $subtypeAttributes, $mediaAttributes, $authorIds);
    }

    public function delete(string $type, Model $record): void
    {
        $disk = $this->registry->for($type)->disk;
        $path = $record->media?->file_path;
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
        $this->mediaRepository->delete($record);
    }

    // ---------------------------------------------------------------------
    // Download (streams the stored PDF — used by GET /{type}/{id}/download)
    // ---------------------------------------------------------------------

    public function download(string $type, string $id): StreamedResponse
    {
        $record = $this->find($type, $id);
        abort_unless($record !== null, 404);

        $disk = $this->registry->for($type)->disk;
        $path = $record->media?->file_path;
        if (!$path || !Storage::disk($disk)->exists($path)) {
            abort(404, 'File not available.');
        }

        $filename = sprintf(
            '%s.pdf',
            preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($record->media?->title ?? '')) ?: $type,
        );

        return Storage::disk($disk)->download($path, $filename);
    }

    // ---------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------

    /**
     * Pull the subtype-specific fields out of the request payload and validate
     * them against the rules declared on the subtype model. The shared fields
     * (title, publication_year, file) are validated by the FormRequest before
     * we ever get here.
     *
     * @param  array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function validateSubtypeFields(string $type, array $attributes): array
    {
        $definition = $this->registry->for($type);
        $subtypeOnly = array_intersect_key($attributes, array_flip($definition->specificFields));

        if ($subtypeOnly === [] || $definition->validationRules === []) {
            return $subtypeOnly;
        }

        $validator = Validator::make($subtypeOnly, $definition->validationRules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Combine pre-existing author IDs with any newly-created author names.
     *
     * @param  array{ids?:int[], new?:string[]} $input
     * @return int[]
     */
    private function resolveAuthorIds(array $input): array
    {
        $ids = array_values(array_unique(array_map('intval', $input['ids'] ?? [])));

        foreach ($input['new'] ?? [] as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            /** @var Author $author */
            $author = $this->authorRepository->create($name);
            $ids[] = $author->id;
        }

        return array_values(array_unique($ids));
    }

    private function storeFile(string $type, UploadedFile $file): string
    {
        $disk = $this->registry->for($type)->disk;

        return $file->store('/', $disk);
    }
}
