<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // Déconnecte les AUTRES sessions du membre (PRD §3.4.5) : re-hash du
        // mot de passe, ce qui périme le `password_hash_web` mémorisé par les
        // sessions ouvertes ailleurs — AuthenticateSession (bootstrap/app.php)
        // les rejette à leur requête suivante. La session courante, elle, voit
        // son empreinte rafraîchie par ce même middleware.
        Auth::logoutOtherDevices($input['password']);

        // Audit RGPD (CLAUDE.md §3.4 / PRD §3.4.5) : trace du changement, sans
        // valeur ni diff — `password` est hors liste blanche `auditLogAttributes()`
        // pour ne jamais journaliser le hash (même le nouveau).
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->event('password_changed')
            ->log('Mot de passe modifié.');
    }
}
