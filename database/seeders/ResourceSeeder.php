<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Models\Resource;
use Illuminate\Database\Seeder;

/**
 * Inventaire physique MVP (PRD §4.6, décision Guillaume 2026-07-03) :
 * 49 bureaux (étage 1 = 29, desk-1 à desk-29 ; étage 2 = 20, desk-30 à desk-49)
 * + 3 salles de réunion + 1 salle événementielle (admin only).
 *
 * `svg_desk_id` est la table de correspondance plan ↔ DB : il vaut exactement
 * l'id de l'élément du SVG versionné (`docs/plan/etages.svg`, ids `desk-N`
 * NON zéro-paddés) — le front cible `#desk-N` / `data-desk`, jamais les ids DB.
 *
 * Les bureaux sont seedés `unassigned` (inventaire physique). L'affectation
 * réelle à un membre (`assignment` + `member_profiles.desk_id`) est posée à
 * l'onboarding, pas ici. Idempotent : updateOrCreate sur `svg_desk_id` (desks)
 * et sur (`type`, `name`) pour les salles.
 */
class ResourceSeeder extends Seeder
{
    private const DESK_COUNT = 49;

    private const FLOOR_ONE_DESK_COUNT = 29;

    public function run(): void
    {
        $this->seedDesks();
        $this->seedMeetingRooms();
        $this->seedEventRoom();
    }

    private function seedDesks(): void
    {
        // Migration douce des ids historiques zéro-paddés (`desk-01`…`desk-09`)
        // vers le format des ids du SVG (`desk-1`…`desk-9`) — idempotent.
        for ($n = 1; $n <= 9; $n++) {
            Resource::where('svg_desk_id', sprintf('desk-%02d', $n))
                ->update(['svg_desk_id' => "desk-{$n}"]);
        }

        for ($n = 1; $n <= self::DESK_COUNT; $n++) {
            Resource::updateOrCreate(['svg_desk_id' => "desk-{$n}"], [
                'type' => ResourceType::Desk->value,
                'name' => "Bureau {$n}",
                'assignment' => ResourceAssignment::Unassigned->value,
                'floor' => $n <= self::FLOOR_ONE_DESK_COUNT ? 1 : 2,
                'is_active' => true,
                'display_order' => $n,
            ]);
        }
    }

    private function seedMeetingRooms(): void
    {
        $rooms = [
            ['name' => 'Salle de réunion 1', 'capacity' => 4, 'display_order' => 101],
            ['name' => 'Salle de réunion 2', 'capacity' => 8, 'display_order' => 102],
            ['name' => 'Salle de réunion 3', 'capacity' => 12, 'display_order' => 103],
        ];

        foreach ($rooms as $room) {
            Resource::updateOrCreate(
                ['type' => ResourceType::MeetingRoom->value, 'name' => $room['name']],
                [
                    'capacity' => $room['capacity'],
                    'external_half_day_price_ht' => 71.00, // PRD §6.3
                    'is_active' => true,
                    'display_order' => $room['display_order'],
                ],
            );
        }
    }

    private function seedEventRoom(): void
    {
        Resource::updateOrCreate(
            ['type' => ResourceType::EventRoom->value, 'name' => 'Salle événementielle'],
            [
                'capacity' => 60,
                'requires_admin' => true, // réservation admin uniquement (PRD §4.6.1)
                'is_active' => true,
                'display_order' => 201,
            ],
        );
    }
}
