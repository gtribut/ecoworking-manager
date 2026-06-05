<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Espace réservable : bureau (48), salle de réunion (3) ou salle event (1).
 * Champs adaptés au `type`. data_model §4.3.
 */
#[Fillable([
    'type', 'name', 'description', 'capacity', 'features', 'assignment', 'floor',
    'svg_desk_id', 'external_half_day_price_ht', 'opening_hours', 'requires_admin',
    'google_calendar_color', 'is_active', 'is_out_of_service', 'display_order',
])]
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'assignment' => ResourceAssignment::class,
            'capacity' => 'integer',
            'features' => 'array',
            'opening_hours' => 'array',
            'external_half_day_price_ht' => 'decimal:2',
            'requires_admin' => 'boolean',
            'is_active' => 'boolean',
            'is_out_of_service' => 'boolean',
        ];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<DeskOccupation, $this> */
    public function deskOccupations(): HasMany
    {
        return $this->hasMany(DeskOccupation::class, 'desk_id');
    }

    /** @return HasMany<DeskAbsence, $this> */
    public function deskAbsences(): HasMany
    {
        return $this->hasMany(DeskAbsence::class, 'desk_id');
    }

    /**
     * Membre dont c'est le bureau attitré (1-1, §6.10).
     *
     * @return HasMany<MemberProfile, $this>
     */
    public function assignedMemberProfile(): HasMany
    {
        return $this->hasMany(MemberProfile::class, 'desk_id');
    }

    /**
     * @param  Builder<resource>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<resource>  $query
     */
    public function scopeOfType(Builder $query, ResourceType $type): void
    {
        $query->where('type', $type->value);
    }
}
