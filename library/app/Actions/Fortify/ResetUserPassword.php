<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, string>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
