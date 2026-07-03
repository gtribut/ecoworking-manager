<x-filament-widgets::widget>
    <x-filament::section heading="Validation des documents internes">
        @if ($stats->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aucun document interne actif publié.
            </p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10" role="list">
                @foreach ($stats as $stat)
                    <li class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $stat['document']->title }}
                                <span class="font-normal text-gray-500 dark:text-gray-400">
                                    (v{{ $stat['document']->version }})
                                </span>
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $stat['validated'] }} / {{ $stat['total'] }} membre(s) actif(s) ont validé
                            </p>
                        </div>
                        <x-filament::badge :color="$stat['rate'] >= 100 ? 'success' : ($stat['rate'] >= 50 ? 'warning' : 'danger')">
                            {{ number_format($stat['rate'], 1, ',', ' ') }} %
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
