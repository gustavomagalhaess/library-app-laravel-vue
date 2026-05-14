<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Author\Repositories\AuthorRepositoryInterface;
use App\Domain\Author\Repositories\EloquentAuthorRepository;
use App\Domain\Book\Models\Book;
use App\Domain\Book\Repositories\BookRepositoryInterface;
use App\Domain\Book\Repositories\EloquentBookRepository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the domain layer interfaces to their concrete implementations and
 * registers the morph map that maps the `mediable_type` discriminator on
 * the `media` table to the concrete subtype model.
 *
 * Controllers and services depend on the interfaces, so swapping in a cache
 * decorator later is a one-line change here. For now the Eloquent repository
 * is bound directly — no caching layer.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookRepositoryInterface::class, EloquentBookRepository::class);
        $this->app->singleton(AuthorRepositoryInterface::class, EloquentAuthorRepository::class);
    }

    public function boot(): void
    {
        // Centralised morph map — keep these aliases short and stable so the
        // `media.mediable_type` column stays decoupled from PHP namespaces.
        // Add new media subtypes here when introducing movies, music, …
        //
        // We use `morphMap` (additive) rather than `enforceMorphMap` (strict)
        // because Spatie's `model_has_*` tables and other framework features
        // still record morph types by FQN; enforcing the map would require
        // every model that ever participates in any morph relation to be
        // registered here.
        Relation::morphMap([
            Book::MORPH_ALIAS => Book::class,
        ]);
    }
}
