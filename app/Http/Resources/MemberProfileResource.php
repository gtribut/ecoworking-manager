<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MemberProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil membre (annuaire + champs perso éditables, PRD §3.4.2). Le bureau
 * attitré et l'entité ne sont exposés qu'en lecture (édition admin).
 *
 * @mixin MemberProfile
 */
final class MemberProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'interests' => $this->interests,
            'linkedin_url' => $this->linkedin_url,
            'website_url' => $this->website_url,
            'birth_date' => $this->birth_date?->toDateString(),
            'photo_path' => $this->photo_path,
            'show_in_directory' => $this->show_in_directory,
            'newsletter_opt_in' => $this->newsletter_opt_in,
            'arrival_date' => $this->arrival_date?->toDateString(),
            'desk' => $this->whenLoaded('desk', fn () => $this->desk === null ? null : [
                'id' => $this->desk->id,
                'name' => $this->desk->name,
                'floor' => $this->desk->floor,
            ]),
        ];
    }
}
