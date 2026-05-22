<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Author\Repositories\AuthorRepositoryInterface;
use App\Domain\Author\Repositories\EloquentAuthorRepository;
use App\Domain\Classification\Repositories\ClassificationRepositoryInterface;
use App\Domain\Classification\Repositories\EloquentClassificationRepository;
use App\Domain\Media\MediaTypeRegistry;
use App\Domain\Media\Repositories\EloquentMediaRepository;
use App\Domain\Media\Repositories\MediaRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the domain layer interfaces to their concrete implementations and
 * registers the morph map that maps the `mediable_type` discriminator on
 * the `media` table to the concrete subtype model.
 *
 * The {@see MediaTypeRegistry} is the single source of truth for which media
 * subtypes exist — it's built from config/media.php and feeds both the morph
 * map and the unified {@see MediaRepositoryInterface}. There are no
 * per-type repository or service bindings: a new media type only needs a
 * model + migration + one line in config/media.php.
 *
 * Controllers and services depend on the interfaces, so swapping in a cache
 * decorator later is a one-line change here. For now the Eloquent repository
 * is bound directly — no caching layer.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The registry is stateless and only inspects config + the registered
        // subtype model classes once, so a singleton is correct here.
        $this->app->singleton(MediaTypeRegistry::class, function (): MediaTypeRegistry {
            /** @var array<string, class-string> $types */
            $types = config('media.types', []);
            return new MediaTypeRegistry($types);
        });

        $this->app->singleton(MediaRepositoryInterface::class, EloquentMediaRepository::class);
        $this->app->singleton(AuthorRepositoryInterface::class, EloquentAuthorRepository::class);
        $this->app->singleton(ClassificationRepositoryInterface::class, EloquentClassificationRepository::class);
    }

    public function boot(): void
    {
        // Centralised morph map — derived from the same registry the rest of
        // the stack reads, so adding a media type only requires editing
        // config/media.php (no provider change needed).
        //
        // We use `morphMap` (additive) rather than `enforceMorphMap` (strict)
        // because Spatie's `model_has_*` tables and other framework features
        // still record morph types by FQN; enforcing the map would require
        // every model that ever participates in any morph relation to be
        // registered here.
        Relation::morphMap(app(MediaTypeRegistry::class)->morphMap());
    }
}
