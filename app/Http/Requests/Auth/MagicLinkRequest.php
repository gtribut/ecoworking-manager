<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de magic link (C12.8a, PRD §3.2). Route invitée (middleware `guest`) :
 * pas d'autorisation métier ici — la vérification d'éligibilité du compte se
 * fait silencieusement dans MagicLinkService (anti-énumération).
 */
final class MagicLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
