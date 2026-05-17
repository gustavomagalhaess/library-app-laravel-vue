# Library App — Laravel + Vue (Inertia) + Docker

Manage a personal library of books and their authors. Built per `docs/PROMPT.md` with:

- PHP 8.3 (Alpine) + Laravel 12 (latest stable)
- Vue 3 + Inertia.js + Vite (Laravel Breeze starter for the auth/UI scaffold)
- Tailwind CSS
- MySQL 8.4 (Alpine) + Redis 7 (Alpine) for caching
- Nginx (Alpine)
- Laravel Fortify (auth, 2FA-ready) + spatie/laravel-permission (roles/permissions)
- Domain-Driven Design layout with Service / Repository / Cache layers

## Project layout

```
.
├── docs/                      # Source spec (PROMPT.md)
├── docker/
│   ├── php/                   # PHP-FPM image (composer + node baked in)
│   ├── nginx/                 # Nginx image + site config
│   └── mysql/init/            # Optional MySQL init scripts
├── library/                   # Laravel application (DDD code lives under library/app/Domain)
│   ├── app/
│   │   ├── Domain/
│   │   │   ├── Author/        # Author model, repository (Eloquent), service
│   │   │   └── Book/          # Book model, repository (Eloquent), service
│   │   ├── Http/Controllers/  # BookController, AuthorController
│   │   ├── Http/Requests/     # FormRequests (Book/Author Store/Update)
│   │   ├── Http/Middleware/   # HandleInertiaRequests with auth/permission sharing
│   │   ├── Actions/Fortify/   # Custom Fortify actions
│   │   └── Providers/         # DomainServiceProvider, FortifyServiceProvider
│   ├── bootstrap/             # app.php (middleware aliases) + providers.php
│   ├── config/                # filesystems.php (books disk), fortify.php
│   ├── database/
│   │   ├── factories/         # Book + Author factories
│   │   ├── migrations/        # books, authors, authors_books
│   │   └── seeders/           # Roles, default users (admin/librarian/reader), books, authors
│   ├── routes/web.php         # All 16 routes from the spec
│   └── resources/js/
│       ├── Pages/             # Books/Index, Books/Save, Authors/Index, Authors/Save
│       └── Components/
│           ├── shared/        # Pagination, SearchBar, BtnEdit, BtnDelete, BtnDownload
│           ├── book/          # BookList, BookForm
│           └── author/        # AuthorList, AuthorForm
├── scripts/install.sh         # One-shot bootstrap (composer create-project + dependencies)
├── docker-compose.yml
├── Makefile                   # `make help` to list every available task
└── .env.example
```

## Quick start

```bash
# 1. Copy env defaults and bootstrap.
cp .env.example .env
make install        # first run takes a few minutes — pulls images, installs deps, seeds DB

# 2. Bring the stack up.
make up             # http://localhost:8080

# 3. Sign in
#    admin@library.local      / password   (full access)
#    librarian@library.local  / password   (no delete)
#    reader@library.local     / password   (read + download)
```

To run the Vite dev server in foreground (HMR enabled for Vue):

```bash
make dev
```

Useful targets:

```bash
make help                          # list all targets
make artisan c="route:list"        # arbitrary artisan command
make composer c="require foo/bar"  # arbitrary composer command
make fresh                         # drop, re-migrate, re-seed
make down                          # stop containers (preserves volumes)
make nuke                          # stop containers + drop all volumes
```

## How the build is layered

`make install` runs `scripts/install.sh`. The Laravel skeleton, custom DDD code, configs, migrations, seeders, Inertia pages, and lockfiles are all committed under `library/`, so the script only needs to do what isn't captured by git:

1. Builds the Docker images.
2. Runs `composer install` inside the PHP container (populates `library/vendor/` from the committed `composer.lock`).
3. Runs `php artisan key:generate` if `APP_KEY` is missing.
4. Creates the gitignored storage runtime directories (`storage/framework/{cache,sessions,views}`, `storage/logs`, `storage/app/private/books`) and runs `php artisan storage:link`.
5. Brings up MySQL + Redis, waits for MySQL to be healthy, then runs `php artisan migrate:fresh --seed --force`.
6. Runs `npm ci` (from the committed `package-lock.json`) and `npm run build`.

Every step is idempotent and non-destructive to committed files — there's no `vendor:publish --force`, no re-scaffolding, no overlays — so you can re-run `make install` safely. The one destructive step on re-run is `migrate:fresh`, which wipes and reseeds the database; use `make migrate` (or `php artisan migrate`) if you want to preserve existing data.

## Architecture highlights

**Domain-Driven Design with a shared Media morph.** `app/Domain/Book` and `app/Domain/Author` are independent bounded contexts. Books are stored as a morph subtype of a shared `media` parent: `media` carries the columns common to every media type (title, publication_year, file_path) and is joined to the subtype table by a UUID that's globally unique across all types. Authors are attached at the media level via the `media_authors` pivot, so the same authors table will serve future media types (movies, music, …) without any schema change.

**Layered request flow.**

```
HTTP request: /{type}/...
    ↓
StoreMediaRequest / UpdateMediaRequest
  (validates the shared media fields + authors,
   checks the {type}-specific permission)
    ↓
MediaController (front controller — match($type) dispatch)
    ↓
Type-specific service (BookService)
  validates type-specific fields (Book: `pages`)
  orchestrates author resolution + file storage
    ↓
Repository interface ← bound directly to EloquentBookRepository (DB)
```

The front controller validates the shared columns and selects the right Domain service per the spec. Each subtype service owns its private fields (and validates them inline). `DomainServiceProvider` wires the repository interfaces to Eloquent implementations and registers the morph map (`mediable_type='book'` ↔ `Book::class`). The repository pattern is in place so a cache decorator can be slotted in later — one-line change in the provider — without touching controllers or services.

**Security.**

- Fortify provides login, registration, password reset, email verification, and 2FA scaffolding.
- spatie/laravel-permission gates every action — the `MediaController` resolves the right permission per `{type}` (e.g. `books.create`) inside `StoreMediaRequest::authorize()` and via `$user->can(...)` in the controller; author routes still use the `can:authors.*` middleware.
- The `books` filesystem disk stores PDFs **outside** the public folder (`storage/app/private/books`) and is exposed only through the authenticated, permissioned `GET /book/{id}/download` route.
- File upload validation only allows `mimes:pdf` (matches the magic bytes, not just the extension).
- Inertia/Vue auto-escapes interpolated strings, mitigating XSS by default; CSRF tokens are sent on every Inertia POST/PUT/DELETE.
- All user input flows through Eloquent (parameterised queries) — no raw SQL string concatenation.

**Performance.**

- Repository pattern in place so a Redis-backed read-through cache can be added later as a decorator without touching controllers/services (deferred).
- Paginated listings (15/page) with `withQueryString()` so search filters survive page navigation.
- Indexed columns on `media.title`, `media.mediable_type`, and `authors.name`.
- Author auto-complete uses a dedicated lightweight endpoint (`/authors/search`) with a 250 ms debounce.

**Queued writes and downloads.**

Every mutation and every download goes through Redis-backed Laravel jobs so the HTTP turnaround stays fast and predictable, even under load. Validation and authorization remain synchronous in the controller; only the work is queued.

```
HTTP request: POST /api/book/...
    ↓
StoreMediaRequest          ── validates + authorizes (synchronous)
    ↓
MediaController            ── stores upload, creates TrackedJob row,
                              dispatches PersistMediaJob, returns
                              202 + { job: { id, status, … } }
    ↓
[Horizon worker on Redis]
    ↓
PersistMediaJob            ── runs domain service inside the worker
                              and updates TrackedJob.status to
                              completed/failed with the result
    ↓
SPA polls GET /api/jobs/{id} every ~1s with exponential ramp,
shows a persistent "Saving…" toast until the job is terminal.
```

The same shape covers create/update/delete for Media and Authors, plus a `PrepareMediaDownloadJob` that issues a short-lived signed URL — the UI shows a "Preparing download…" toast that resolves into a "Download now" link when the job completes. Job classes live under `app/Domain/Jobs/Jobs/`; the lifecycle bookkeeping is centralised in the `TracksProgress` trait. The worker runs in its own container (`library-worker`) via `php artisan horizon` against the `media`, `downloads`, `authors` and `default` queues.

## Running tests

```bash
make test
```

## Routes

Media routes are served by the single `MediaController` front controller. The `{type}` segment selects which Domain service handles the request; for now only `book` is registered (extend `whereIn` in `routes/web.php` and the morph map + match arms to add more types).

| Method | URI                          | Name              | Permission       |
|--------|------------------------------|-------------------|------------------|
| GET    | `/{type}`                    | `media.index`     | `{type}s.view`   |
| GET    | `/{type}/create`             | `media.create`    | `{type}s.create` |
| POST   | `/{type}`                    | `media.store`     | `{type}s.create` |
| GET    | `/{type}/{id}/edit`          | `media.edit`      | `{type}s.update` |
| PUT    | `/{type}/{id}`               | `media.update`    | `{type}s.update` |
| DELETE | `/{type}/{id}`               | `media.destroy`   | `{type}s.delete` |
| GET    | `/{type}/{id}/download`      | `media.download`  | `{type}s.download` |
| GET    | `/{type}/search`             | `media.search`    | `{type}s.view`   |
| GET    | `/authors`                   | `authors.index`   | `authors.view`   |
| GET    | `/authors/create`            | `authors.create`  | `authors.create` |
| POST   | `/authors`                   | `authors.store`   | `authors.create` |
| GET    | `/authors/{id}/edit`         | `authors.edit`    | `authors.update` |
| PUT    | `/authors/{id}`              | `authors.update`  | `authors.update` |
| DELETE | `/authors/{id}`              | `authors.destroy` | `authors.delete` |
| GET    | `/authors/search`            | `authors.search`  | `authors.view`   |

Concretely with `{type}=book` today: `/book`, `/book/create`, `/book/{uuid}/edit`, etc. Permission strings stay pluralised (`books.view`, `books.create`, …) for backwards compatibility with the seeded roles. `{id}` is the media UUID (matches `books.uuid` and `media.uuid`).

Plus the auth routes registered automatically by Fortify.

## Extending to other media types (movies, music, …)

Books and the future media types share the back-end front controller (`MediaController`) and the shared `media` table, so adding a new type is small and surgical:

1. **Schema.** New subtype table mirroring `books`: a `uuid` PK plus type-specific columns. No changes to `media`, `authors`, or `media_authors` — those tables already cover the new type.
2. **Domain.** New folder `app/Domain/Movie` (model, repository interface + Eloquent impl, service). The Model only needs the morphOne to Media: `morphOne(Media::class, 'mediable', 'mediable_type', 'uuid', 'uuid')`. Shared metadata (`title`, `publication_year`, `file_path`) and authors are reached through the relation — `$movie->media->title`, `$movie->media->authors` — so no per-type proxy accessors are required.
3. **Wiring.** Bind the new repository interface in `DomainServiceProvider::register()` and add the morph alias (`'movie' => Movie::class`) to the `enforceMorphMap` call in `boot()`.
4. **Front controller.** Add `'movie'` to the `whereIn('type', […])` constraint in `routes/web.php`, then extend the match arms in `MediaController` (`serviceFor`, `pageFor`, `typeSpecificFields`, `diskFor`, `permission`, `collectionKey`, `singleKey`). Each new arm is a one-liner.
5. **Type-specific validation** goes in the new service (e.g. `MovieService::validateMovieSpecific()`), keeping `StoreMediaRequest`/`UpdateMediaRequest` focused on the columns that exist on `media`.
6. **Permissions + seeds.** Add the `movies.*` permissions to `RolesAndPermissionsSeeder` and update the `match` arms in the FormRequests/controller. Seeders/factories follow the same pattern as `BookFactory::configure()` — create the subtype row and let an `afterCreating` hook create the matching `media` row with the same UUID.
7. **Front-end.** Reuse `Components/shared/*` (Pagination, SearchBar, Btn*) and follow the `Components/book/*` pattern, passing `{ type: 'movie' }` to the named routes (`route('media.index', { type: 'movie' })`).
