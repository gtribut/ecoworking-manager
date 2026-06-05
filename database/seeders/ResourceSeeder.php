<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ResourceAssignment;
use App\Enums\ResourceType;
use App\Models\Resource;
use Illuminate\Database\Seeder;

/**
 * Inventaire physique MVP (PRD §4.6) : 48 bureaux (étages 1 & 2) + 3 salles de
 * réunion + 1 salle événementielle (admin only).
 *
 * Les bureaux sont seedés `unassigned` (inventaire physique). L'affectation
 * réelle à un membre (`assignment` + `member_profiles.desk_id`) est posée à
 * l'onboarding, pas ici. Idempotent : updateOrCreate sur `svg_desk_id` (desks)
 * et sur (`type`, `name`) pour les salles.
 */
class ResourceSeeder extends Seeder
{
    private const DESK_COUNT = 48;

    public function run(): void
    {
        $this->seedDesks();
        $this->seedMeetingRooms();
        $this->seedEventRoom();
    }

    private function seedDesks(): void
    {
        for ($n = 1; $n <= self::DESK_COUNT; $n++) {
            $svgId = sprintf('desk-%02d', $n);

            Resource::updateOrCreate(['svg_desk_id' => $svgId], [
                'type' => ResourceType::Desk->value,
                'name' => "Bureau {$n}",
                'assignment' => ResourceAssignment::Unassigned->value,
                'floor' => $n <= 24 ? 1 : 2,
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
