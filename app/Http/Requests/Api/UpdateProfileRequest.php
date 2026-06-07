<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Édition du profil membre par le membre lui-même (PRD §3.4.2 / §3.4.4, PATCH
 * partiel). Périmètre volontairement restreint aux champs personnels : le nom
 * est en lecture seule (édition admin), et l'email / mot de passe relèvent de
 * flux dédiés avec ré-authentification (§3.4.5, Fortify) — donc hors de ce
 * Form Request. L'auto-scope (toujours `$request->user()`) garantit qu'un
 * membre n'édite que son propre profil (CLAUDE.md §3.1).
 */
final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Édition de son propre profil : l'authentification Sanctum suffit, le
        // contrôleur opère exclusivement sur l'utilisateur courant.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'theme' => ['sometimes', 'nullable', 'string', 'in:light,dark'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Préférences de notification (PRD §3.8.4) — toggles indépendants,
            // portés par `users`, activés par défaut.
            'notify_email' => ['sometimes', 'boolean'],
            'notify_in_app' => ['sometimes', 'boolean'],
            'interests' => ['sometimes', 'nullable', 'string', 'max:200'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'show_in_directory' => ['sometimes', 'boolean'],
            'newsletter_opt_in' => ['sometimes', 'boolean'],
        ];
    }
}
