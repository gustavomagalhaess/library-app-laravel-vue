<?php

namespace App\Domain\Media\Services;

use App\Domain\Book\Models\Book;
use App\Domain\Book\Services\BookService;
use App\Domain\Media\Exceptions\MediaException;
use App\Domain\Media\Messages\MediaMessage;
use App\Domain\ModelInterface;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Contracts\Container\BindingResolutionException;

class MediaService
{
    private const string SERVICE_PREFIX = "App\\Domain";
    private const string SERVICE_SUFFIX = 'Service';

    public function validatedSearchTerm(string $term): ?string
    {
        // Per spec, search requires at least 3 characters; below that we treat it as no filter.
        return mb_strlen($term) >= 3 ? $term : null;
    }

    /**
     * Instantiate the service accordingly with the type.
     */
    public function serviceFor(string $type): object
    {
        try {
            $mediaType = Str::ucfirst(Str::lower($type));
            $className = $mediaType . self::SERVICE_SUFFIX;
            $namespace = self::SERVICE_PREFIX . "\\$mediaType\\Services\\" . $className;

            return app($namespace);
        } catch (BindingResolutionException $e) {
            app('log')->error(
                MediaMessage::UNSUPORTED_MEDIA_TYPE,
                ['type' => $type, 'exception' => $e]
            );

            throw new MediaException(MediaMessage::UNSUPORTED_MEDIA_TYPE);
        }
    }

    /**
     * @return string[]
     */
    public function typeSpecificFields(string $type): array
    {
        /** @var ModelInterface $model */
        $model = Str::ucfirst(Str::lower($type));

        return $model::getSpecificFields();
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

    public function pageFor(string $type, string $page): string
    {
        $mediaType = Str::ucfirst(Str::plural(Str::lower($type)));
        $pageName = Str::ucfirst($page);

        return "$mediaType/$pageName";
    }

    public function download(string $type, string $id): StreamedResponse
    {
        try {
            $service = $this->serviceFor($type);
            $record = $service->find($id);
            abort_unless($record !== null, 404);

            // Every media subtype exposes ->media (the morphOne to the shared
            // Media row), so title/file_path live there regardless of $type.
            $path = $record->media?->file_path;
            $disk = Str::plural(Str::lower($type));
            if (!$path || !Storage::disk($disk)->exists($path)) {
                abort(404, 'File not available.');
            }

            $filename = sprintf(
                '%s.pdf',
                preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($record->media?->title ?? '')) ?: $type,
            );

            return Storage::disk($disk)->download($path, $filename);
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
