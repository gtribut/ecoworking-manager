<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskAbsences\Pages;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Filament\Resources\DeskAbsences\DeskAbsenceResource;
use App\Models\DeskAbsence;
use App\Services\PresenceService;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Correction d'une absence par l'admin (PRD §4.8.2, audit log obligatoire —
 * assuré par le trait Auditable du modèle). Passe par `PresenceService`.
 */
class EditDeskAbsence extends EditRecord
{
    protected static string $resource = DeskAbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var DeskAbsence $record */
        return app(PresenceService::class)->updateAbsence($record, [
            'date_start' => CarbonImmutable::parse((string) $data['date_start']),
            'date_end' => isset($data['date_end']) ? CarbonImmutable::parse((string) $data['date_end']) : null,
            'period' => self::enumValue(Period::class, $data['period']),
            'recurrence_type' => self::enumValue(DeskAbsenceRecurrence::class, $data['recurrence_type']),
            'recurrence_day_of_week' => isset($data['recurrence_day_of_week']) ? (int) $data['recurrence_day_of_week'] : null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * L'état du formulaire Filament restitue tantôt la valeur brute, tantôt
     * l'enum (casts du modèle) : on normalise avant d'appeler le service.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    private static function enumValue(string $enum, mixed $value): \BackedEnum
    {
        return $value instanceof $enum ? $value : $enum::from((string) $value);
    }
}
