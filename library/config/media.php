<?php

declare(strict_types=1);
use App\Domain\Book\Models\Book;

/*
|--------------------------------------------------------------------------
| Media subtype registry
|--------------------------------------------------------------------------
|
| Every supported media type maps a short morph alias (used as the URL
| segment and the `media.mediable_type` discriminator) to its concrete
| subtype model. Adding a new media type is meant to be a *one-line* change
| in this file, plus a Model class + migration. No new controller, route,
| repository, service, or FormRequest is required.
|
| The model class must implement {@see \App\Domain\Media\Contracts\MediaSubtype},
| which exposes the per-type metadata that the rest of the stack needs:
|   - getMorphAlias()         — short alias (e.g. 'book')
|   - getDisk()               — filesystem disk (e.g. 'books')
|   - getSpecificFields()     — subtype-only columns ($fillable on the subtype)
|   - getValidationRules()    — Laravel rules for those columns
|
| This config is read by:
|   - {@see \App\Domain\Media\MediaTypeRegistry}  — the runtime lookup
|   - {@see \App\Providers\DomainServiceProvider} — builds the morphMap
|   - routes/web.php + routes/api.php             — the `whereIn` constraint
|
*/

return [
    'types' => [
        'book' => Book::class,
        // 'movie' => \App\Domain\Movie\Models\Movie::class,
        // 'music' => \App\Domain\Music\Models\Music::class,
    ],
];
