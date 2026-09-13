<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeskOccupationSource;
use App\Enums\DeskOccupationStatus;
use App\Enums\Period;
use App\Models\Concerns\Auditable;
use Database\Factories\DeskOccupationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Occupation effective d'un bureau, par demi-journée. Stocke surtout les
 * `external_ticket` ; la présence des résidents est dérivée. data_model §4.3.
 */
#[Fillable([
    'desk_id', 'user_id', 'date', 'period', 'source', 'ticket_id', 'status', 'created_by',
])]
class DeskOccupation extends Model
{
    /** @use HasFactory<DeskOccupationFactory> */
    use Auditable, HasFactory;

    /**
     * Encore annulable ? Renseigné PAR LOT depuis l'API (une requête pour toute
     * la page, cf. DeskController), jamais persisté : cf. `scopeCancellable`.
     */
    public ?bool $cancellable = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period' => Period::class,
            'source' => DeskOccupationSource::class,
            'status' => DeskOccupationStatus::class,
        ];
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

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return list<string>
     */
    protected function auditLogAttributes(): array
    {
        return ['desk_id', 'date', 'period', 'status', 'ticket_id'];
    }

    /**
     * Occupation encore annulable (PRD §3.5.5/§3.5.9, délai Q22 transposé aux
     * bureaux) : PRÉSENTE (jamais déjà annulée — review lot E pt.1, sinon une
     * occupation annulée dans les délais restait « cancellable » et une
     * double annulation pouvait restituer un ticket déjà repris ailleurs), ET
     * date future, ou date du jour et la demi-journée n'a pas encore commencé
     * (matin : avant 9h, après-midi : avant 14h, heure de Paris ; `full_day`
     * suit le seuil du matin, puisqu'elle démarre à 9h).
     *
     * Comparaison de la colonne DATE liée sur `today()->toDateString()` (PHP,
     * app Europe/Paris) — PAS `CURRENT_DATE` (SQL) : la session Postgres est en
     * UTC (review lot E pt.2), donc entre 00h et 02h heure de Paris,
     * `CURRENT_DATE` désigne encore la veille et rouvrirait à tort l'occupation
     * de la veille. Convention du dépôt (DeskAbsence::scopeNotStartedBefore,
     * AdminDashboardService, UpdateOverdueInvoicesCommand) : comparer une
     * colonne DATE à une date PHP bindée, jamais à une fonction SQL liée à la
     * session. L'heure du jour, elle, vient de l'horloge PHP courante
     * (`now()->hour`) : ce n'est pas une lecture de ligne DB fraîchement
     * écrite, donc pas sujette au piège timezone du dépôt.
     *
     * @param  Builder<DeskOccupation>  $query
     */
    public function scopeCancellable(Builder $query): void
    {
        $hour = now()->hour;
        $today = today()->toDateString();

        $periodsNotStarted = match (true) {
            $hour < 9 => [Period::Morning->value, Period::Afternoon->value, Period::FullDay->value],
            $hour < 14 => [Period::Afternoon->value],
            default => [],
        };

        $query->where('status', DeskOccupationStatus::Present->value)
            ->where(function (Builder $q) use ($today, $periodsNotStarted): void {
                $q->where('date', '>', $today);

                if ($periodsNotStarted !== []) {
                    $q->orWhere(function (Builder $qq) use ($today, $periodsNotStarted): void {
                        $qq->where('date', '=', $today)->whereIn('period', $periodsNotStarted);
                    });
                }
            });
    }
}
