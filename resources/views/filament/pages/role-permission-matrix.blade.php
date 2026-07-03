<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Composition figée dans le code (PRD §2.5 / §2.6, seedée par
            <code>PermissionSeeder</code>) — cette vue est en lecture seule.
            L'affectation des rôles à un membre se fait depuis sa fiche
            (Membres &amp; entités → Comptes) ; les rôles d'usage
            (résident / additionnel / externe / équipe) y sont exclusifs entre eux.
        </p>
    </x-filament::section>

    @if ($roles->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aucun rôle en base : exécuter les seeders (<code>RoleSeeder</code>,
                <code>PermissionSeeder</code>).
            </p>
        </x-filament::section>
    @else
        <x-filament::section heading="Matrice rôles → permissions">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <caption class="sr-only">
                        Permissions accordées à chaque rôle. Une coche indique que le rôle détient la permission.
                    </caption>
                    <thead>
                        <tr class="border-b border-gray-200 text-start dark:border-white/10">
                            <th scope="col" class="px-3 py-2 text-start font-medium">Permission</th>
                            @foreach ($roles as $role)
                                <th scope="col" class="px-3 py-2 text-center font-medium">{{ $role->getLabel() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($matrix as $permission => $grants)
                            <tr>
                                <th scope="row" class="px-3 py-2 text-start font-medium">
                                    <code>{{ $permission }}</code>
                                    @if (in_array($permission, $adminPermissions, true))
                                        <x-filament::badge color="gray" class="ms-1">back-office</x-filament::badge>
                                    @endif
                                </th>
                                @foreach ($roles as $role)
                                    <td class="px-3 py-2 text-center">
                                        @if ($grants[$role->value])
                                            <span class="font-semibold text-success-600 dark:text-success-400" aria-hidden="true">✓</span>
                                            <span class="sr-only">accordée</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-600" aria-hidden="true">—</span>
                                            <span class="sr-only">non accordée</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
