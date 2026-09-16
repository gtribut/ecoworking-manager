<x-filament-panels::page>
    {{-- Sélecteur de date (aujourd'hui par défaut, PRD §4.8.4) --}}
    <div class="max-w-xs">
        <label for="occupancy-date" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
            Date affichée
        </label>
        <x-filament::input.wrapper>
            <x-filament::input
                id="occupancy-date"
                type="date"
                wire:model.live="date"
            />
        </x-filament::input.wrapper>
    </div>

    @unless ($occupancy['is_working_day'])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Jour non ouvré (week-end ou férié) : les bureaux nomades ne sont pas réservables.
                Les résidents restent présents par défaut sur leur bureau attitré, sauf absence
                déclarée.
            </p>
        </x-filament::section>
    @endunless

    <x-filament::section heading="Capacité bureaux libres restante">
        <p class="text-sm text-gray-950 dark:text-white">
            <span class="text-2xl font-semibold">{{ $occupancy['available_desks_count'] }}</span>
            bureau(x) libre(s) encore disponible(s) en journée entière.
        </p>
    </x-filament::section>

    @forelse ($occupancy['floors'] as $floor => $groups)
        <x-filament::section :heading="'Étage '.$floor">
            <div class="space-y-6">
                @if ($groups['assigned'] !== [])
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Bureaux attitrés</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-start text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-start dark:border-white/10">
                                        <th scope="col" class="px-3 py-2 text-start font-medium">Bureau</th>
                                        <th scope="col" class="px-3 py-2 text-start font-medium">Résident</th>
                                        <th scope="col" class="px-3 py-2 text-start font-medium">Statut</th>
                                        <th scope="col" class="px-3 py-2 text-start font-medium">Utilisé par</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach ($groups['assigned'] as $entry)
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ $entry['desk']->name }}</td>
                                            <td class="px-3 py-2">{{ $entry['resident']?->fullName() ?? '—' }}</td>
                                            <td class="px-3 py-2">
                                                @if ($entry['status'] === 'present')
                                                    <x-filament::badge color="success">Présent</x-filament::badge>
                                                @elseif ($entry['status'] === 'absent')
                                                    <x-filament::badge color="danger">Absent</x-filament::badge>
                                                @else
                                                    <x-filament::badge color="gray">Pas d'info</x-filament::badge>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                @forelse ($entry['occupations'] as $occupation)
                                                    <span>
                                                        {{ $occupation->user?->fullName() ?? '—' }}
                                                        ({{ $occupation->period->getLabel() }},
                                                        {{ $occupation->source->getLabel() }})
                                                    </span>
                                                    @if (! $loop->last)<br>@endif
                                                @empty
                                                    —
                                                @endforelse
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($groups['unassigned'] !== [])
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Bureaux libres</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-start text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-start dark:border-white/10">
                                        <th scope="col" class="px-3 py-2 text-start font-medium">Bureau</th>
                                        <th scope="col" class="px-3 py-2 text-start font-medium">Occupation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach ($groups['unassigned'] as $entry)
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ $entry['desk']->name }}</td>
                                            <td class="px-3 py-2">
                                                @forelse ($entry['occupations'] as $occupation)
                                                    <span>
                                                        {{ $occupation->user?->fullName() ?? '—' }}
                                                        ({{ $occupation->period->getLabel() }})
                                                    </span>
                                                    @if (! $loop->last)<br>@endif
                                                @empty
                                                    <x-filament::badge color="success">Disponible</x-filament::badge>
                                                @endforelse
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucun bureau actif.</p>
        </x-filament::section>
    @endforelse

    <x-filament::section heading="Réservations salles du jour">
        @if ($occupancy['room_bookings']->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune réservation de salle ce jour.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-start dark:border-white/10">
                            <th scope="col" class="px-3 py-2 text-start font-medium">Salle</th>
                            <th scope="col" class="px-3 py-2 text-start font-medium">Créneau</th>
                            <th scope="col" class="px-3 py-2 text-start font-medium">Qui</th>
                            <th scope="col" class="px-3 py-2 text-start font-medium">Libellé</th>
                            <th scope="col" class="px-3 py-2 text-start font-medium">Ticket</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($occupancy['room_bookings'] as $booking)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $booking->resource?->name ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    {{ $booking->starts_at->format('H:i') }} – {{ $booking->ends_at->format('H:i') }}
                                </td>
                                <td class="px-3 py-2">{{ $booking->user?->fullName() ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $booking->title ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $booking->ticket_id !== null ? 'Oui' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
