<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Single-action endpoint for "delete my account".
 *
 * Fortify ships every other piece of account management we use (registration,
 * email verification, password reset/update, profile information update, 2FA),
 * but account deletion is intentionally left to the application because the
 * destruction policy varies wildly per project (soft vs hard delete, related
 * data cleanup, audit trails, …). For the Library app a hard delete after a
 * current-password check is the right behaviour.
 */
class DeleteAccountController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::guard('web')->logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
