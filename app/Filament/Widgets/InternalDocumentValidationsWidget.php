<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\Widget;

/**
 * Bloc d'alerte « Documents internes non validés par X% des membres »
 * (C12.6, PRD §4.1.2). Widget MINCE : stats dans AdminDashboardService.
 */
class InternalDocumentValidationsWidget extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.internal-document-validations';

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'stats' => app(AdminDashboardService::class)->internalDocumentValidationStats(),
        ];
    }
}
