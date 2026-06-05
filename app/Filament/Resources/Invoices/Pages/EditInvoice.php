<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\IssueInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Édition d'un brouillon de facture (InvoicePolicy::update ⇒ draft uniquement).
 * Une fois émise, la facture devient figée et se consulte sur ViewInvoice.
 */
class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Émission définitive : pose le numéro et fige les montants (§3.6).
            Action::make('issue')
                ->label('Émettre la facture')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->color('success')
                ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft)
                ->requiresConfirmation()
                ->modalDescription('Le numéro sera attribué définitivement et les montants figés. Action irréversible.')
                ->action(function (Invoice $record): void {
                    app(IssueInvoiceService::class)->issue($record, Auth::id());
                })
                ->successNotificationTitle('Facture émise')
                ->successRedirectUrl(fn (Invoice $record): string => static::getResource()::getUrl('view', ['record' => $record])),

            DeleteAction::make(),
        ];
    }
}
