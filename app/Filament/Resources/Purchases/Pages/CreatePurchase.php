<?php

declare(strict_types=1);

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Services\PurchaseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    /**
     * Trace l'admin qui crédite l'achat (MVP : crédité manuellement, §4.2).
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
     * Passe par le service pour générer les tickets du bénéficiaire dans la
     * même transaction que l'achat (un INSERT `purchases` seul laisserait un
     * solde de tickets à zéro).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(PurchaseService::class)->createWithTickets($data);
    }
}
