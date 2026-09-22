<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        // SSO-gebundene Konten (Entra) dürfen kein lokales Passwort erhalten –
        // sonst könnte man am Entra-Gate vorbei lokal einloggen.
        if ($user->entra_object_id !== null) {
            throw ValidationException::withMessages([
                'email' => 'Für SSO-Konten kann kein lokales Passwort gesetzt werden.',
            ]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
