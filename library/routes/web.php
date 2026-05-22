<?php

declare(strict_types=1);

use App\Http\Controllers\Account\DeleteAccountController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\MediaController;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
]))->name('home');

/*
|--------------------------------------------------------------------------
| Profile (auth only — verified middleware is intentionally omitted so an
| unverified user can still reach /profile to fix the email address that's
| blocking their verification email).
|--------------------------------------------------------------------------
|
| Profile information updates and password changes live on Fortify's native
| routes (user-profile-information.update, user-password.update); we only
| need a page to host the Vue forms and a dedicated delete-account endpoint.
*/
Route::middleware('auth')->group(function (): void {
    Route::get('/profile', function (Request $request) {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    })->name('profile.edit');

    Route::delete('/user', DeleteAccountController::class)->name('current-user.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Media (front controller — currently `book`, extendable to movie/music/…)
    |--------------------------------------------------------------------------
    |
    | Web routes are read-only: they render the Inertia index page, expose the
    | JSON search endpoint, and stream file downloads. All mutations
    | (store/update/delete) live in routes/api.php and are called by the SPA
    | from the modal forms hosted on the index page.
    |
    | The {type} segment selects which Domain service handles the request.
    | The `whereIn` constraint also keeps these routes from swallowing the
    | author URLs below — only known media types are matched.
    |
    */
    Route::prefix('{type}')
        // Allow every type registered in config/media.php — adding 'movie'
        // there is enough to enable /movie, /movie/search, /movie/{id}/download.
        ->whereIn('type', array_keys((array) config('media.types', [])))
        ->group(function (): void {
            // Search must be declared *before* the {id} routes so `/book/search`
            // isn't captured by `/{type}/{id}/...`.
            Route::get('search', [MediaController::class, 'search'])
                ->middleware('can:media.view,type')
                ->name('media.search');

            Route::get('/', [MediaController::class, 'index'])
                ->middleware('can:media.view,type')
                ->name('media.index');

            Route::get('{id}/download', [MediaController::class, 'download'])
                ->middleware('can:media.download,type')
                ->name('media.download');
        });

    /*
    |--------------------------------------------------------------------------
    | Authors  (Authors are shared across all media types)
    |--------------------------------------------------------------------------
    */
    Route::get('authors/search', [AuthorController::class, 'search'])
        ->middleware('can:authors.view')
        ->name('authors.search');

    Route::get('authors', [AuthorController::class, 'index'])
        ->middleware('can:authors.view')
        ->name('authors.index');

    Route::get('classifications', [ClassificationController::class, 'index'])
        ->name('classifications.index');
});

/*
|--------------------------------------------------------------------------
| Signed-URL download endpoint
|--------------------------------------------------------------------------
|
| Reached by the SPA after PrepareMediaDownloadJob resolves with a signed
| URL. The signature middleware enforces expiry + tamper-resistance, so we
| don't apply auth/verified middleware here — the URL itself is the
| capability and it's scoped to a specific TrackedJob.
*/
Route::get('jobs/media/{type}/{id}/download/{job}', [JobsController::class, 'download'])
    ->whereIn('type', array_keys((array) config('media.types', [])))
    ->middleware('signed')
    ->name('jobs.media.download');

// Fortify auth routes (login, register, password reset, email verification,
// profile-information update, password update, password confirmation, 2FA)
// are registered automatically by Laravel\Fortify\FortifyServiceProvider —
// no manual require.

// Fortify auth routes (login, register, password reset, email verification,
// profile-information update, password update, password confirmation, 2FA)
// are registered automatically by Laravel\Fortify\FortifyServiceProvider —
// no manual require.
