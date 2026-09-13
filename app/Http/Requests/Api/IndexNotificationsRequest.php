<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pagination du centre de notifications (PRD §3.8.4). La cloche charge les
 * pages à la demande (« Charger plus », `useInfiniteQuery`) : `page` et
 * `per_page` sont validés ici, jamais lus bruts dans le contrôleur
 * (CLAUDE.md §3.2). Aucun paramètre ne touche à l'isolation — le périmètre
 * reste `$request->user()->notifications()`.
 */
final class IndexNotificationsRequest extends FormRequest
{
    /** Garde-fou de pagination : la cloche pagine par 20. */
    public const int MAX_PER_PAGE = 50;

    public const int DEFAULT_PER_PAGE = 20;

    /** Route déjà protégée par `auth:sanctum` ; aucune autorisation de plus. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,'.self::MAX_PER_PAGE],
        ];
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : self::DEFAULT_PER_PAGE;
    }
}
