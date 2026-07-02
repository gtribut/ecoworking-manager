<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Pages;

use App\Exceptions\BookingConflictException;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Resource;
use App\Models\User;
use App\Services\BookingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Trace l'admin qui crée la réservation depuis le back-office (§2.6).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    /**
     * Passe par BookingService (verrou anti-double-booking + backstop GiST) :
     * un INSERT direct renverrait une QueryException brute (500) en cas de
     * chevauchement. Le conflit devient une erreur de formulaire.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BookingService::class)->create([
                'resource' => Resource::query()->findOrFail($data['resource_id']),
                'user' => isset($data['user_id']) ? User::query()->findOrFail($data['user_id']) : null,
                'starts_at' => Carbon::parse($data['starts_at']),
                'ends_at' => Carbon::parse($data['ends_at']),
                'title' => $data['title'] ?? null,
                'billable' => $this->resolveBillable($data),
                'is_internal' => (bool) ($data['is_internal'] ?? false),
                'price_ht' => $data['price_ht'] ?? null,
                'vat_rate' => $data['vat_rate'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        } catch (BookingConflictException $e) {
            throw ValidationException::withMessages(['data.starts_at' => $e->getMessage()]);
        }
    }

    /**
     * Résout l'entité facturée du MorphToSelect (`billable_type`/`billable_id`).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveBillable(array $data): ?Model
    {
        if (empty($data['billable_type']) || empty($data['billable_id'])) {
            return null;
        }

        /** @var class-string<Model> $class */
        $class = Relation::getMorphedModel($data['billable_type']) ?? $data['billable_type'];

        return $class::query()->findOrFail($data['billable_id']);
    }
}
