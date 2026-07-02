<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskOccupations\Pages;

use App\Filament\Resources\DeskOccupations\DeskOccupationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateDeskOccupation extends CreateRecord
{
    protected static string $resource = DeskOccupationResource::class;

    /**
     * Trace l'admin qui déclare l'occupation depuis le back-office (§2.6).
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
     * Traduit la violation de l'exclusion `desk_occupations_no_overlap` en
     * erreur de formulaire (sinon : QueryException brute → 500).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01' || str_contains($e->getMessage(), 'desk_occupations_no_overlap')) {
                throw ValidationException::withMessages([
                    'data.period' => 'Ce bureau est déjà occupé sur ce créneau.',
                ]);
            }

            throw $e;
        }
    }
}
