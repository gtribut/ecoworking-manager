<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload de la photo de profil (PRD §3.4.2) : JPG/PNG/WebP, 2 Mo maximum,
 * 80×80 px minimum (plus petit rendu produit — en dessous, l'agrandissement
 * serait illisible). L'autorisation est portée par la route (`auth:sanctum`)
 * et le contrôleur, toujours auto-scopé sur l'utilisateur authentifié.
 */
final class StoreProfilePhotoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                // Borne HAUTE indispensable : un PNG « bombe de décompression »
                // de 12000×12000 pèse 17 Ko (il passe `max:2048`) mais coûte
                // ~770 Mo et 9 s de CPU au décodage. Le contrôle de dimensions
                // s'appuie sur getimagesize() — l'en-tête seul, jamais le pixel.
                'dimensions:min_width=80,min_height=80,max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'Formats acceptés : JPG, PNG ou WebP.',
            'photo.max' => 'La photo ne doit pas dépasser 2 Mo.',
            'photo.dimensions' => 'La photo doit mesurer entre 80 × 80 et 4000 × 4000 pixels.',
        ];
    }
}
