<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeskAbsenceRecurrence;
use App\Enums\Period;
use App\Models\Concerns\Auditable;
use App\Observers\DeskAbsenceObserver;
use Database\Factories\DeskAbsenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Déclaration d'absence d'un résident/staff sur SON bureau. Expansion des
 * récurrences à la lecture (jamais de pré-génération). data_model §4.3.
 */
#[Fillable([
    'desk_id', 'user_id', 'date_start', 'date_end', 'period',
    'recurrence_type', 'recurrence_day_of_week', 'notes', 'created_by',
])]
#[ObservedBy(DeskAbsenceObserver::class)]
class DeskAbsence extends Model
{
    /** @use HasFactory<DeskAbsenceFactory> */
    use Auditable, HasFactory;

    /**
     * Droits du MEMBRE propriétaire sur cette absence, renseignés PAR LOT depuis
     * l'API (une requête pour toute la page), jamais persistés : la comparaison
     * « l'absence a-t-elle commencé ? » se fait côté SQL sur les colonnes DATE.
     */
    public ?bool $canEdit = null;

    public ?bool $canDelete = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'period' => Period::class,
            'recurrence_type' => DeskAbsenceRecurrence::class,
            'recurrence_day_of_week' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditLogAttributes(): array
    {
        return [
            'desk_id', 'date_start', 'date_end', 'period',
            'recurrence_type', 'recurrence_day_of_week', 'notes',
        ];
    }

    /**
     * Absences à venir ou EN COURS (PRD §3.4.6, liste par défaut du portail).
     *
     * Comparaison en SQL contre la date du jour (jamais `isPast()` en PHP sur
     * une ligne fraîche : piège fuseau du dépôt). Trois cas : plage/récurrence
     * bornée non terminée, jour unique à venir, récurrence hebdo sans fin.
     *
     * Chaque clause est NULL-SAFE (`whereNotNull` explicite) : sans cela une
     * ligne à `date_end` NULL rendrait la disjonction NULL, et sa NÉGATION
     * (`whereNot(upcoming)`, filtre « Terminées » du back-office) resterait
     * NULL → la ligne disparaîtrait des DEUX filtres.
     *
     * @param  Builder<DeskAbsence>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $today = today()->toDateString();

        $query->where(function (Builder $scoped) use ($today): void {
            $scoped->where(fn (Builder $bounded) => $bounded
                ->whereNotNull('date_end')
                ->where('date_end', '>=', $today))
                ->orWhere(fn (Builder $single) => $single
                    ->whereNull('date_end')
                    ->where('date_start', '>=', $today))
                ->orWhere(fn (Builder $endless) => $endless
                    ->whereNull('date_end')
                    ->where('recurrence_type', DeskAbsenceRecurrence::Weekly->value));
        });
    }

    /**
     * Absence dont le jour de début n'est pas dépassé : fenêtre d'action du
     * membre (PRD §3.4.6, « possible jusqu'au début »), jour de début inclus —
     * même borne pour la modification et la suppression, sans quoi supprimer
     * puis re-déclarer contournerait la borne d'édition.
     *
     * @param  Builder<DeskAbsence>  $query
     */
    public function scopeNotStartedBefore(Builder $query): void
    {
        $query->where('date_start', '>=', today()->toDateString());
    }

    /** @return BelongsTo<resource, $this> */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'desk_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
