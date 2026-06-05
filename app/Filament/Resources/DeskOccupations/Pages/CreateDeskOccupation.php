<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeskOccupations\Pages;

use App\Filament\Resources\DeskOccupations\DeskOccupationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

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
}
