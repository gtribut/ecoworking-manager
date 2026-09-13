<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskAbsences\Pages;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Exceptions\DomainActionException;
use App\Filament\Resources\DeskAbsences\DeskAbsenceResource;
use App\Models\User;
use App\Services\PresenceService;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Saisie d'une absence par l'admin pour un membre (PRD §4.8.2). La création
 * passe par `PresenceService` (bureau déduit du profil) — aucune logique
 * métier dans la Resource. La notification admin (Q25) n'est PAS envoyée :
 * elle n'existe que pour signaler une déclaration faite depuis le portail.
 */
class CreateDeskAbsence extends CreateRecord
{
    protected static string $resource = DeskAbsenceResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = User::query()->findOrFail($data['user_id']);

        try {
            return app(PresenceService::class)->declareAbsence([
                'user' => $user,
                'date_start' => CarbonImmutable::parse((string) $data['date_start']),
                'date_end' => isset($data['date_end']) ? CarbonImmutable::parse((string) $data['date_end']) : null,
                'period' => self::enumValue(Period::class, $data['period']),
                'recurrence_type' => self::enumValue(DeskAbsenceRecurrence::class, $data['recurrence_type']),
                'recurrence_day_of_week' => isset($data['recurrence_day_of_week']) ? (int) $data['recurrence_day_of_week'] : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        } catch (DomainActionException $e) {
            throw ValidationException::withMessages(['data.user_id' => $e->getMessage()]);
        }
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
