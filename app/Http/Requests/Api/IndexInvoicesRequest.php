<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Tri, filtres et recherche de la liste des factures du portail (PRD §3.6.2).
 *
 * Toutes les entrées sont validées ici : le contrôleur ne lit jamais la requête
 * brute (CLAUDE.md §3.2). Aucun de ces paramètres ne touche au périmètre
 * d'isolation — celui-ci est appliqué en premier par le contrôleur et les
 * filtres ne peuvent que le restreindre.
 */
final class IndexInvoicesRequest extends FormRequest
{
    /** Colonnes triables exposées au portail (PRD §3.6.2). */
    public const array SORTS = ['issued_at', 'number', 'status'];

    /** Garde-fou de pagination : la SPA pagine par 20. */
    public const int MAX_PER_PAGE = 100;

    /** Le module administratif est réservé au rôle `billing_contact` (PRD §3.6.1). */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `nullable` partout : la SPA peut envoyer un paramètre vide (un
            // select remis à « Tous »), que ConvertEmptyStringsToNull
            // transforme en null — un 422 casserait la page pour rien.
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            // `month` seul n'a pas de sens (on ne balaie pas tous les ans) :
            // `nullable` plutôt que `sometimes`, sinon `required_with` ne se
            // déclencherait jamais (la règle entière serait sautée).
            'year' => ['nullable', 'required_with:month', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            // Jamais `draft` : les brouillons n'existent pas côté membre.
            'status' => ['nullable', Rule::in(self::memberVisibleStatuses())],
            'q' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.required_with' => 'Le filtre par mois nécessite une année.',
        ];
    }

    /**
     * Statuts visibles d'un membre : tous sauf le brouillon (PRD §3.6.2).
     *
     * @return list<string>
     */
    public static function memberVisibleStatuses(): array
    {
        return array_values(array_filter(
            InvoiceStatus::values(),
            static fn (string $status): bool => $status !== InvoiceStatus::Draft->value,
        ));
    }

    public function sort(): string
    {
        $sort = $this->string('sort')->toString();

        return in_array($sort, self::SORTS, true) ? $sort : 'issued_at';
    }

    public function direction(): string
    {
        return $this->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
    }

    public function year(): ?int
    {
        return $this->filled('year') ? $this->integer('year') : null;
    }

    public function month(): ?int
    {
        return $this->filled('month') ? $this->integer('month') : null;
    }

    public function status(): ?string
    {
        $status = $this->string('status')->toString();

        return $status === '' ? null : $status;
    }

    /**
     * Fragment de numéro recherché, jokers LIKE neutralisés : un `%` ou un `_`
     * saisi par l'utilisateur reste un caractère littéral (`\` = caractère
     * d'échappement par défaut de Postgres).
     */
    public function numberSearch(): ?string
    {
        $search = trim($this->string('q')->toString());

        return $search === '' ? null : addcslashes($search, '\\%_');
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : 20;
    }
}
