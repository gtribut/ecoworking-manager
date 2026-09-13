<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MemberProfile;
use App\Services\Profile\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entrée de l'annuaire des coworkers (C12.5, PRD §3.7) : projection minimale
 * d'un profil OPT-IN (`show_in_directory`). Jamais d'email ni de téléphone —
 * le bouton « Contacter » (mailto) du PRD §3.7.4 est un 🟡 non validé, aucun
 * opt-in dédié n'existe au MVP. Le contrôleur ne sert cette resource qu'à
 * travers le scope `inDirectory()`.
 *
 * @mixin MemberProfile
 */
final class DirectoryEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'photo' => ProfilePhotoService::urls($this->photo_path, $this->user->id),
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'interests' => $this->interests,
            'linkedin_url' => $this->linkedin_url,
            'website_url' => $this->website_url,
            'company' => $this->whenLoaded('company', fn (): ?string => $this->company?->name),
            'desk' => $this->whenLoaded('desk', fn (): ?array => $this->desk === null ? null : [
                'svg_desk_id' => $this->desk->svg_desk_id,
                'name' => $this->desk->name,
                'floor' => $this->desk->floor,
            ]),
        ];
    }
}
