<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLog\Tables;

use App\Filament\Resources\ActivityLog\ActivityResource;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Listing de l'audit log (PRD §4.14) : filtres sujet (type + recherche par id),
 * auteur, événement, période, et recherche dans le contenu des changements.
 * Perf : pagination Filament + eager loading des morphs causer/subject
 * (sinon N+1 sur chaque ligne).
 */
class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('causer_id')
                    ->label('Par')
                    // Causer absent = action système (cron, seeder, console).
                    ->formatStateUsing(fn (Activity $record): string => $record->causer instanceof User
                        ? $record->causer->fullName()
                        : 'Système')
                    ->placeholder('Système'),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::eventLabels()[$state] ?? ($state ?? '—'))
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Modèle')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => ActivityResource::subjectTypeLabel($state)),
                TextColumn::make('subject_id')
                    ->label('Sujet')
                    ->formatStateUsing(fn (Activity $record): string => ActivityResource::subjectDisplay($record))
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Modèle')
                    ->options(ActivityResource::subjectTypeLabels()),
                SelectFilter::make('event')
                    ->label('Action')
                    ->options(self::eventLabels()),
                SelectFilter::make('causer_id')
                    ->label('Auteur')
                    // Les causers sont toujours des users (résolveur spatie par
                    // défaut = utilisateur authentifié) ; ~50 membres, preload OK.
                    ->options(fn (): array => User::query()
                        ->orderBy('last_name')->orderBy('first_name')
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->fullName()} ({$user->email})"])
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('causer_type', 'user')->where('causer_id', $data['value'])
                        : $query),
                Filter::make('created_at')
                    ->label('Période')
                    ->schema([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('until')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from): Builder => $q->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until): Builder => $q->whereDate('created_at', '<=', $until)))
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['from'] ?? null) && filled($data['until'] ?? null) => "Du {$data['from']} au {$data['until']}",
                        filled($data['from'] ?? null) => "Depuis le {$data['from']}",
                        filled($data['until'] ?? null) => "Jusqu'au {$data['until']}",
                        default => null,
                    }),
                Filter::make('changes')
                    ->label('Contenu des changements')
                    ->schema([
                        TextInput::make('value')
                            ->label('Recherche dans les changements')
                            ->placeholder('ex. inactive, email…'),
                    ])
                    // Recherche full-text dans le diff (PRD §4.14). whereRaw avec
                    // binding (pas d'injection) : cast jsonb → text, spécifique
                    // Postgres (SGBD unique du projet, dev/test/prod).
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, string $value): Builder => $q
                            ->whereRaw('attribute_changes::text ILIKE ?', ['%'.$value.'%'])))
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null)
                        ? "Changements : « {$data['value']} »"
                        : null),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    /**
     * Libellés FR des événements spatie/activitylog.
     *
     * @return array<string, string>
     */
    private static function eventLabels(): array
    {
        return [
            'created' => 'Création',
            'updated' => 'Modification',
            'deleted' => 'Suppression',
            'restored' => 'Restauration',
        ];
    }
}
