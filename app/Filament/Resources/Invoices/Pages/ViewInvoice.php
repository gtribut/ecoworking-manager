<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\CancelInvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Consultation d'une facture émise (figée). L'édition est réservée aux
 * brouillons (InvoicePolicy::update) ; c'est donc ici, sur la vue lecture seule,
 * qu'on héberge l'annulation → avoir d'une facture déjà émise (§3.6).
 */
class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Annulation d'une facture émise = passage cancelled + avoir (§3.6).
            Action::make('cancel')
                ->label('Annuler + générer l\'avoir')
                ->icon(Heroicon::OutlinedDocumentMinus)
                ->color('danger')
                ->visible(fn (Invoice $record): bool => $record->number !== null
                    && $record->status !== InvoiceStatus::Cancelled
                    && ! $record->is_credit_note)
                ->schema([
                    Textarea::make('reason')
                        ->label('Motif de l\'annulation')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalDescription('Un avoir miroir sera émis et la facture passera en « annulée ». Action irréversible.')
                ->action(function (Invoice $record, array $data): void {
                    app(CancelInvoiceService::class)->cancel($record, $data['reason'] ?? null, Auth::id());
                })
                ->successNotificationTitle('Facture annulée, avoir généré')
                ->successRedirectUrl(fn (Invoice $record): string => static::getResource()::getUrl('view', ['record' => $record])),
        ];
    }
}
