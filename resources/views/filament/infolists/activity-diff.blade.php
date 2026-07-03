{{-- Diff avant/après d'une entrée d'audit (colonne `attribute_changes`). --}}
@php
    /** @var \Illuminate\Support\Collection|null $state */
    $state = $getState();
    $changes = $state?->toArray() ?? [];
    $after = $changes['attributes'] ?? [];
    $before = $changes['old'] ?? [];
    $fields = array_values(array_unique([...array_keys($after), ...array_keys($before)]));

    $format = static function (mixed $value): string {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'oui' : 'non',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    };
@endphp

@if ($fields === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Aucun changement d'attribut enregistré pour cet événement.
    </p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-start text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-start dark:border-white/10">
                    <th scope="col" class="px-3 py-2 text-start font-medium">Champ</th>
                    <th scope="col" class="px-3 py-2 text-start font-medium">Avant</th>
                    <th scope="col" class="px-3 py-2 text-start font-medium">Après</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($fields as $field)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $field }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                            {{ $format($before[$field] ?? null) }}
                        </td>
                        <td class="px-3 py-2">{{ $format($after[$field] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
