# Library App — Laravel + Vue (Inertia) + Docker

Manage a personal library of books and their authors. Built per `docs/PROMPT.md` with:

- PHP 8.3 (Alpine) + Laravel 12 (latest stable)
- Vue 3 + Inertia.js + Vite (Laravel Breeze starter for the auth/UI scaffold)
- Tailwind CSS
- MySQL 8.4 (Alpine) + Redis 7 (Alpine) for cache, sessions, and queues
- Nginx (Alpine)
- Laravel Fortify (auth, 2FA-ready) + spatie/laravel-permission (roles/permissions)
- Laravel Horizon (Redis queue worker + dashboard) + Laravel Telescope (request/job/log inspector)
- Domain-Driven Design layout with Service / Repository / Cache layers and Redis-backed queued writes

## Project layout

```
.
├── docker/
│   ├── php/                           # PHP-FPM image (composer + node + chromium baked in)
│   ├── nginx/                         # Nginx image + site config
│   └── mysql/init/                    # MySQL init scripts (creates the `library_dusk` DB)
├── library/                           # Laravel application (DDD code lives under library/app/Domain)
│   ├── app/
│   │   ├── Domain/
│   │   │   ├── Author/                # Author model, repository (Eloquent), service, exceptions
│   │   │   ├── Book/                  # Book subtype model
│   │   │   ├── Classification/        # Classification model, repository, service
│   │   │   │                          #   (read-only Dewey Decimal lookup — seeded once, never mutated)
│   │   │   ├── Media/                 # Shared front-end of the polymorphic media stack:
│   │   │   │                          #   MediaTypeRegistry, MediaService, repository,
│   │   │   │                          #   MediaSubtype contract, exceptions, message strings
│   │   │   └── Jobs/                  # Queued-write infrastructure:
│   │   │       ├── Models/            #   TrackedJob (status row the SPA polls)
│   │   │       ├── Concerns/          #   TracksProgress trait (lifecycle bookkeeping)
│   │   │       ├── Jobs/              #   PersistMediaJob, DeleteMediaJob, PrepareMediaDownloadJob,
│   │   │       │                      #     PersistAuthorJob, DeleteAuthorJob
│   │   │       └── Services/          #   JobDispatcher (controller → job glue)
│   │   ├── Http/Controllers/          # MediaController, AuthorController, ClassificationController,
│   │   │                              #   JobsController, DashboardController, Account/DeleteAccountController
│   │   ├── Http/Requests/             # FormRequests (Media/Author Store/Update)
│   │   ├── Http/Middleware/           # HandleInertiaRequests (shares auth.user/roles/permissions)
│   │   ├── Policies/                  # MediaPolicy (type-aware gates)
│   │   ├── Actions/Fortify/           # Custom Fortify actions
│   │   └── Providers/                 # DomainServiceProvider, FortifyServiceProvider,
│   │                                  #   HorizonServiceProvider, TelescopeServiceProvider
│   ├── bootstrap/                     # app.php (middleware aliases) + providers.php
│   ├── config/                        # media.php, fortify.php, horizon.php, dusk.php, …
│   ├── database/
│   │   ├── factories/                 # Book + Author + User factories
│   │   ├── migrations/                # users, jobs, books, authors, media, media_authors,
│   │   │                              #   permission_tables, tracked_jobs
│   │   └── seeders/                   # Roles, default users (admin/librarian/reader), books, authors
│   ├── routes/
│   │   ├── web.php                    # Inertia + read endpoints + signed-URL downloads
│   │   └── api.php                    # SPA mutations (return 202 + TrackedJob)
│   ├── resources/js/
│   │   ├── Pages/                     # Books/Index, Authors/Index, Auth/*, Profile/*, Dashboard
│   │   ├── Layouts/                   # AuthenticatedLayout, GuestLayout
│   │   ├── Components/
│   │   │   ├── shared/                # Pagination, SearchBar, DeleteButton, DownloadButton (queued),
│   │   │   │                          #   ConfirmModal, Toaster
│   │   │   ├── book/                  # BookList, BookForm
│   │   │   └── author/                # AuthorList, AuthorForm
│   │   └── composables/               # useToasts (loading/action toasts), useJobTracker (polling)
│   └── tests/
│       ├── Feature/                   # API + queued-job tests (Bus::fake assertions)
│       └── Browser/                   # Dusk browser tests (Auth/LoginTest, …)
├── scripts/install.sh                 # One-shot bootstrap (composer create-project + dependencies)
├── docker-compose.yml                 # app, web, db, redis, worker, node
├── Makefile                           # `make help` to list every available task
└── .env.example
```

## Quick start

```bash
# 1. Copy env defaults and bootstrap.
cp .env.example .env
make install        # first run takes a few minutes — pulls images, installs deps, seeds DB

# 2. Bring the stack up (includes the Horizon worker container).
make up             # http://localhost:8080

# 3. Sign in
#    admin@library.local      / password   (full access, plus Horizon + Telescope)
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
make horizon                       # tail the queue worker logs
make horizon-restart               # gracefully restart Horizon workers (picks up code changes)
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

**Domain-Driven Design with a shared Media morph.** `app/Domain/Book`, `app/Domain/Author`, `app/Domain/Classification`, and `app/Domain/Media` are independent bounded contexts. Books are stored as a morph subtype of a shared `media` parent: `media` carries the columns common to every media type (title, publication_year, file_path) and is joined to the subtype table by a UUID that's globally unique across all types. Authors are attached at the media level via the `media_authors` pivot, so the same authors table will serve future media types (movies, music, …) without any schema change. Classifications (Dewey Decimal categories) are similarly attached at the media level via the `media_classifications` pivot — they are seeded once and never mutated through the UI; the `ClassificationService` exposes a read-only `list()` method used by the book form's category picker.

**Layered request flow (synchronous reads).**

```
HTTP GET: /{type}/...
    ↓
MediaController::{index,search,download}
    ↓
MediaService                        ── one service per registered subtype
    ↓
MediaRepositoryInterface ← EloquentMediaRepository
```

The front controller validates the shared columns and routes to the unified `MediaService`; the `MediaTypeRegistry` (built once from `config/media.php`) resolves the subtype model, disk, and per-type validation rules. `DomainServiceProvider` wires the repository interface to the Eloquent implementation and registers the morph map (`mediable_type='book'` ↔ `Book::class`). The repository pattern is in place so a cache decorator can be slotted in later — one-line change in the provider — without touching controllers or services.

**Queued writes and downloads (Redis-backed).**

Every mutation and every download goes through Redis-backed Laravel jobs so the HTTP turnaround stays fast and predictable, even under load. Validation and authorization remain synchronous in the controller; only the work is queued.

```
HTTP POST: /api/book/...
    ↓
StoreMediaRequest                ── validates + authorizes (synchronous)
    ↓
MediaController                  ── stores the upload, asks JobDispatcher to
                                     create a TrackedJob row + dispatch the
                                     matching job, returns 202 + { job: {…} }
    ↓
JobDispatcher ─→ PersistMediaJob │ DeleteMediaJob │ PersistAuthorJob │
                  DeleteAuthorJob │ PrepareMediaDownloadJob
    ↓
[ Horizon worker on the `media` / `authors` / `downloads` Redis queue ]
    ↓
Job::handle()                    ── runs the domain service inside the worker
                                     and writes the result (record / signed URL)
                                     back to TrackedJob.result; status flips
                                     queued → processing → completed | failed
```

The lifecycle bookkeeping (status transitions, error capture, failed() hook) is centralised in the `TracksProgress` trait so adding a new job class only means writing its `run()` method. Job classes live under `app/Domain/Jobs/Jobs/`, and `JobDispatcher` (`app/Domain/Jobs/Services/JobDispatcher.php`) is the single point that controllers call. Downloads use the same primitive: `PrepareMediaDownloadJob` builds a short-lived signed URL into `JobsController::download` and returns it in `result.url`.

**SPA persistence + optimistic UI.**

The Vue/Inertia frontend never blocks on a queued write. When the user submits a form or triggers a download:

1. The page POSTs to `/api/...` and immediately gets back `202 + { job: { id, status, … } }`.
2. The modal closes; a persistent "Saving…" / "Preparing download…" toast appears with a spinner. The `useToasts` composable (`resources/js/composables/useToasts.js`) owns these long-lived toasts.
3. `useJobTracker.trackJob()` (`resources/js/composables/useJobTracker.js`) polls `GET /api/jobs/{uuid}` on an exponential ramp (~750 ms → 2.5 s) until the status is terminal.
4. On `completed`, the toast resolves to success — for downloads, it morphs into a "Download now" link pointing at the signed URL the job produced. Inertia partial-reloads the list (`router.reload({ only: ['books'] })`) so the table reflects the worker's persisted state.
5. On `failed`, the toast resolves to error with the user-facing message from the job's `TrackedJob.message`.

This means the UI never freezes waiting for I/O, and a long-running write (file upload + author resolution + multi-table insert) feels as snappy as a synchronous response.

**Observability: Horizon + Telescope.**

Both dashboards ship with the app and are intended as developer/admin tools — not user-facing features:

- **Horizon** at `/horizon` — live view of queued jobs, throughput, failed jobs, retry tools. The `library-worker` Docker service runs `php artisan horizon`, which auto-balances workers across the `media`, `downloads`, `authors`, and `default` queues defined in `config/horizon.php`.
- **Telescope** at `/telescope` — request/response inspector, query log, exception viewer, cache + Redis + mail + job activity. Useful for debugging the queued-write flow because every dispatched job + DB write shows up with its full context.

Access is restricted to the `admin` role via the `viewHorizon` / `viewTelescope` gates in their respective service providers. We override the default authorization callbacks (`Horizon::auth()` / `Telescope::auth()`) so the gate fires in **every** environment, not just non-local — the framework defaults short-circuit to "allowed" whenever `APP_ENV=local`, which would let any logged-in user (or none at all) reach the dashboards in dev.

**Security.**

- Fortify provides login, registration, password reset, email verification, and 2FA scaffolding.
- spatie/laravel-permission gates every action — the `MediaPolicy` resolves the right permission per `{type}` (e.g. `books.create`) and is invoked by the `can:media.*,type` middleware on both `routes/web.php` and `routes/api.php`. Author routes still use the `can:authors.*` middleware.
- The `books` filesystem disk stores PDFs **outside** the public folder (`storage/app/private/books`) and is exposed only through the authenticated, permissioned `GET /book/{id}/download` route plus the per-job signed URL minted by `PrepareMediaDownloadJob`.
- File upload validation only allows `mimes:pdf` (matches the magic bytes, not just the extension).
- Inertia/Vue auto-escapes interpolated strings, mitigating XSS by default; CSRF tokens are sent on every Inertia POST/PUT/DELETE.
- All user input flows through Eloquent (parameterised queries) — no raw SQL string concatenation.

**Performance.**

- Repository pattern in place so a Redis-backed read-through cache can be added later as a decorator without touching controllers/services (deferred).
- Paginated listings (15/page) with `withQueryString()` so search filters survive page navigation.
- Indexed columns on `media.title`, `media.mediable_type`, and `authors.name`.
- Author auto-complete uses a dedicated lightweight endpoint (`/authors/search`) with a 250 ms debounce.

## Running tests

```bash
make test               # PHPUnit feature + unit tests

# Browser tests (Dusk). Run once before the first invocation:
make dusk-setup         # creates the `library_dusk` DB and builds Vite assets
make dusk               # runs `php artisan dusk` inside the app container
make dusk c="--filter LoginTest"   # forward extra args to the dusk binary
```

Dusk runs Chromium headless inside the `app` container against the nginx `web` service. The `.env.dusk.local` override points `APP_URL` at `http://web` so the browser can reach the SPA across the docker-compose bridge (a Chromium running inside `app` cannot reach `http://localhost:8080` — that port is exposed on the host, not on the container's loopback).

## Routes

Two route files, split by responsibility:

### `routes/web.php` — Inertia pages, read endpoints, signed downloads

Inertia renders the SPA shell and the search/download endpoints stay on the web stack so they use the session cookie directly. The `{type}` segment selects which media subtype the request targets and is constrained by `whereIn(array_keys(config('media.types')))` — for now only `book` is registered.

| Method | URI                                              | Name                    | Permission              |
|--------|--------------------------------------------------|-------------------------|-------------------------|
| GET    | `/`                                              | `home`                  | guest                   |
| GET    | `/dashboard`                                     | `dashboard`             | auth, verified          |
| GET    | `/profile`                                       | `profile.edit`          | auth                    |
| DELETE | `/user`                                          | `current-user.destroy`  | auth                    |
| GET    | `/{type}`                                        | `media.index`           | `media.view`            |
| GET    | `/{type}/search`                                 | `media.search`          | `media.view`            |
| GET    | `/{type}/{id}/download`                          | `media.download`        | `media.download`        |
| GET    | `/authors`                                       | `authors.index`         | `authors.view`          |
| GET    | `/authors/search`                                | `authors.search`        | `authors.view`          |
| GET    | `/classifications`                               | `classifications.index` | auth, verified          |
| GET    | `/jobs/media/{type}/{id}/download/{job}`         | `jobs.media.download`   | signed URL              |
| GET    | `/horizon`                                       | (Horizon UI)            | `viewHorizon` (admin)   |
| GET    | `/telescope`                                     | (Telescope UI)          | `viewTelescope` (admin) |

The Fortify auth routes (`login`, `register`, `password.request`, `password.reset`, `verification.notice`, `verification.verify`, `user-profile-information.update`, `user-password.update`, `two-factor.*`) are registered automatically and aren't repeated here.

### `routes/api.php` — SPA mutations (return 202 + TrackedJob)

Every mutation is queued. The controller validates the request synchronously, dispatches a job, and responds with `202 Accepted` plus a `{ job: { id, status, … } }` envelope. The SPA polls `GET /api/jobs/{uuid}` until the job's status is terminal.

| Method | URI                                  | Name                         | Permission         |
|--------|--------------------------------------|------------------------------|--------------------|
| POST   | `/api/{type}`                        | `api.media.store`            | `media.create`     |
| POST   | `/api/{type}/{id}`                   | `api.media.update`           | `media.update`     |
| DELETE | `/api/{type}/{id}`                   | `api.media.destroy`          | `media.delete`     |
| POST   | `/api/{type}/{id}/download`          | `api.media.download.request` | `media.download`   |
| POST   | `/api/authors`                       | `api.authors.store`          | `authors.create`   |
| PUT    | `/api/authors/{author}`              | `api.authors.update`         | `authors.update`   |
| DELETE | `/api/authors/{author}`              | `api.authors.destroy`        | `authors.delete`   |
| GET    | `/api/jobs/{uuid}`                   | `api.jobs.show`              | owner or admin     |

All `/api/*` routes are auth-guarded via the Sanctum stateful guard (`auth:sanctum`) + `verified`. Update is POST (not PUT) on the media routes because multipart/form-data file uploads can't ride a browser PUT; Laravel's `_method` tunneling is a web-stack convenience that we avoid in the JSON API for clarity. `{id}` is the media UUID (matches `books.uuid` and `media.uuid`).

## Extending to other media types (movies, music, …)

Books and the future media types share the back-end front controller (`MediaController`) and the shared `media` table, so adding a new type is small and surgical:

1. **Schema.** New subtype table mirroring `books`: a `uuid` PK plus type-specific columns. No changes to `media`, `authors`, or `media_authors` — those tables already cover the new type.
2. **Domain.** New folder `app/Domain/Movie` with the subtype model. The model implements `MediaSubtype` (declares `getMorphAlias()`, `getDisk()`, `getSpecificFields()`, `getValidationRules()`) and has a morphOne to Media: `morphOne(Media::class, 'mediable', 'mediable_type', 'uuid', 'uuid')`. Shared metadata (`title`, `publication_year`, `file_path`) and authors are reached through the relation — `$movie->media->title`, `$movie->media->authors` — so no per-type proxy accessors are required.
3. **Wiring.** Add `'movie' => Movie::class` to `config/media.php`. The `MediaTypeRegistry` picks it up automatically and feeds the morph map, the routes' `whereIn` constraint, the policy, and the unified `MediaService` — no provider, controller, or service changes are needed.
4. **Permissions + seeds.** Add the `movies.*` permissions to `RolesAndPermissionsSeeder`. Seeders/factories follow the same pattern as `BookFactory::configure()` — create the subtype row and let an `afterCreating` hook create the matching `media` row with the same UUID.
5. **Front-end.** Reuse `Components/shared/*` (Pagination, SearchBar, Btn*, DownloadButton, Toaster) and follow the `Components/book/*` pattern, passing `{ type: 'movie' }` to the named routes (`route('media.index', { type: 'movie' })` and `route('api.media.store', { type: 'movie' })`).
