<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog;

use App\Filament\Resources\ActivityLog\Pages\ListActivities;
use App\Filament\Resources\ActivityLog\Pages\ViewActivity;
use App\Filament\Resources\ActivityLog\Schemas\ActivityInfolist;
use App\Filament\Resources\ActivityLog\Tables\ActivitiesTable;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Audit log (C12.8b, PRD §4.14) : consultation LECTURE SEULE des actions
 * tracées par spatie/activitylog (trait Auditable sur les entités sensibles,
 * CLAUDE.md §3.4). Aucune création/édition/suppression — verrouillé par
 * ActivityPolicy (admin-only en lecture, écriture interdite à tous).
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Système';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'entrée d\'audit';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?string $navigationLabel = 'Audit log';

    /**
     * Libellés FR des types de sujets audités (alias de la morph map,
     * AppServiceProvider). Sert aussi d'options au filtre « Modèle ».
     *
     * @return array<string, string>
     */
    public static function subjectTypeLabels(): array
    {
        return [
            'user' => 'Compte membre',
            'company' => 'Entité facturée',
            'subscription' => 'Abonnement',
            'purchase' => 'Achat',
            'booking' => 'Réservation',
            'invoice' => 'Facture',
            'payment' => 'Paiement',
        ];
    }

    /** Libellé FR d'un alias morph (fallback : l'alias brut). */
    public static function subjectTypeLabel(?string $alias): string
    {
        return self::subjectTypeLabels()[$alias] ?? ($alias ?? '—');
    }

    /**
     * Représentation lisible du sujet : « Type #id — nom » quand le modèle
     * est encore chargeable, « Type #id » sinon (sujet supprimé/anonymisé).
     */
    public static function subjectDisplay(Activity $activity): string
    {
        $base = sprintf('%s #%s', self::subjectTypeLabel($activity->subject_type), $activity->subject_id ?? '?');

        $name = match (true) {
            $activity->subject instanceof User => $activity->subject->fullName(),
            $activity->subject instanceof Company => $activity->subject->name,
            $activity->subject instanceof Invoice => $activity->subject->number,
            default => null,
        };

        return $name !== null && $name !== '' ? "{$base} — {$name}" : $base;
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    /** Lecture seule : jamais de création manuelle d'une entrée d'audit. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
