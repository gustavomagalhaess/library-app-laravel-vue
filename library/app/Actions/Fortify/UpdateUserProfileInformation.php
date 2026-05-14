<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  User  $user
     * @param  array<string,string>  $input
     */
    public function update($user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'email_verified_at' => null,
            ])->save();
            $user->sendEmailVerificationNotification();
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }
}
