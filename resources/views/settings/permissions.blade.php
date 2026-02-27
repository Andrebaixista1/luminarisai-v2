<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Permiss&otilde;es
        </h2>
    </x-slot>

    @php
        $defaultHierarchy = $hierarchies[0]['key'] ?? 'master';
        $defaultPageByHierarchy = [];
        $firstPageKey = $permissionTree[0]['key'] ?? '';
        foreach ($hierarchies as $hierarchy) {
            $defaultPageByHierarchy[$hierarchy['key']] = $firstPageKey;
        }
    @endphp

    <div
        class="py-8"
        x-data="{
            activeHierarchy: @js($defaultHierarchy),
            selectedPageByHierarchy: @js($defaultPageByHierarchy),
            setHierarchyAll(hierarchyKey, value) {
                document.querySelectorAll(`[data-hierarchy='${hierarchyKey}']`).forEach((item) => {
                    item.checked = value;
                });
            },
            setPageActions(hierarchyKey, pageKey, value) {
                document.querySelectorAll(`[data-hierarchy='${hierarchyKey}'][data-parent-page='${pageKey}']`).forEach((item) => {
                    item.checked = value;
                });
            }
        }"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden border border-gray-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="px-6 py-4 border-b border-gray-300 bg-gray-100 dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Controle de acesso por hierarquia</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Estilo em colunas: Hierarquia | P&aacute;ginas | A&ccedil;&otilde;es.</p>
                </div>

                <div class="p-5">
                    @if (session('status') === 'permissions-updated')
                        <div class="mb-4 border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                            Permiss&otilde;es atualizadas com sucesso.
                        </div>
                    @endif

                    @if (count($permissionTree) === 0)
                        <div class="border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            Nenhuma permiss&atilde;o encontrada nas rotas protegidas.
                        </div>
                    @else
                        <form method="post" action="{{ route('settings.permissions.update') }}" class="space-y-4">
                            @csrf

                            <div class="border border-gray-300 dark:border-slate-700 overflow-x-auto">
                                <div class="min-w-[1180px]">
                                <div class="grid grid-cols-12 bg-gray-100 border-b border-gray-300 text-xs font-semibold uppercase tracking-wide text-gray-800">
                                    <div class="px-4 py-2 border-r border-gray-300 dark:border-slate-600 col-span-2">Hierarquias</div>
                                    <div class="px-4 py-2 border-r border-gray-300 dark:border-slate-600 col-span-4">P&aacute;ginas</div>
                                    <div class="px-4 py-2 col-span-6">A&ccedil;&otilde;es da p&aacute;gina</div>
                                </div>

                                <div class="grid grid-cols-12 min-h-[560px]">
                                    <aside class="border-r border-gray-300 bg-gray-100 dark:border-slate-700 dark:bg-slate-800 col-span-2">
                                        @foreach ($hierarchies as $hierarchy)
                                            @php
                                                $hierarchyKey = $hierarchy['key'];
                                                $total = count($matrix[$hierarchyKey] ?? []);
                                                $enabled = count(array_filter($matrix[$hierarchyKey] ?? []));
                                            @endphp
                                            <button
                                                type="button"
                                                class="w-full border-b border-gray-300 px-4 py-3 text-left dark:border-slate-700"
                                                x-bind:class="activeHierarchy === '{{ $hierarchyKey }}'
                                                    ? 'bg-white text-gray-900 dark:bg-slate-900 dark:text-slate-100'
                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                                x-on:click="activeHierarchy = '{{ $hierarchyKey }}'"
                                            >
                                                <p class="text-base font-semibold">{{ $hierarchy['label'] }}</p>
                                                <p class="text-xs">{{ $enabled }}/{{ $total }} ativas</p>
                                            </button>
                                        @endforeach
                                    </aside>

                                    <section class="border-r border-gray-300 bg-white dark:border-slate-700 dark:bg-slate-900 col-span-4">
                                        @foreach ($hierarchies as $hierarchy)
                                            @php $hierarchyKey = $hierarchy['key']; @endphp
                                            <div x-show="activeHierarchy === '{{ $hierarchyKey }}'" class="h-full">
                                                <div class="px-4 py-3 border-b border-gray-300 bg-gray-50 flex flex-wrap gap-2 dark:border-slate-700 dark:bg-slate-800">
                                                    <button type="button" class="border border-gray-400 bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600" x-on:click="setHierarchyAll('{{ $hierarchyKey }}', true)">Habilitar tudo</button>
                                                    <button type="button" class="border border-gray-400 bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600" x-on:click="setHierarchyAll('{{ $hierarchyKey }}', false)">Desabilitar tudo</button>
                                                </div>

                                                <div class="max-h-[500px] overflow-y-auto">
                                                    @foreach ($permissionTree as $page)
                                                        @php
                                                            $pageEnabled = (bool) ($matrix[$hierarchyKey][$page['key']] ?? false);
                                                        @endphp
                                                        <button
                                                            type="button"
                                                        class="w-full border-b border-gray-200 px-4 py-3 text-left dark:border-slate-700"
                                                        x-bind:class="selectedPageByHierarchy['{{ $hierarchyKey }}'] === '{{ $page['key'] }}'
                                                            ? 'bg-gray-100 dark:bg-slate-700'
                                                            : 'bg-white hover:bg-gray-50 dark:bg-slate-900 dark:hover:bg-slate-800'"
                                                        x-on:click="selectedPageByHierarchy['{{ $hierarchyKey }}'] = '{{ $page['key'] }}'"
                                                    >
                                                            <div class="flex items-start gap-3">
                                                                <input
                                                                    type="checkbox"
                                                                    name="permissions[{{ $hierarchyKey }}][{{ $page['key'] }}]"
                                                                    value="1"
                                                                    data-hierarchy="{{ $hierarchyKey }}"
                                                                    class="mt-1 h-4 w-4 rounded border-gray-400 text-gray-800 focus:ring-gray-500"
                                                                    @checked($pageEnabled)
                                                                >
                                                                <span>
                                                                    <span class="block text-sm font-semibold text-gray-800 dark:text-slate-100">{{ $page['label'] }}</span>
                                                                    <span class="block text-xs text-gray-500 dark:text-slate-400">{{ $page['group'] }}</span>
                                                                </span>
                                                            </div>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </section>

                                    <section class="bg-white dark:bg-slate-900 col-span-6">
                                        @foreach ($hierarchies as $hierarchy)
                                            @php $hierarchyKey = $hierarchy['key']; @endphp
                                            <div x-show="activeHierarchy === '{{ $hierarchyKey }}'" class="h-full">
                                                @foreach ($permissionTree as $page)
                                                    <div x-show="selectedPageByHierarchy['{{ $hierarchyKey }}'] === '{{ $page['key'] }}'" class="h-full">
                                                        <div class="px-4 py-3 border-b border-gray-300 bg-gray-50 dark:border-slate-700 dark:bg-slate-800">
                                                            <p class="text-base font-semibold text-gray-800 dark:text-slate-100">{{ $page['label'] }}</p>
                                                            <p class="text-xs text-gray-500 mt-1 dark:text-slate-400">A&ccedil;&otilde;es permitidas nessa p&aacute;gina</p>
                                                        </div>

                                                        <div class="px-4 py-3 border-b border-gray-200 flex flex-wrap gap-2 dark:border-slate-700">
                                                            <button type="button" class="border border-gray-400 bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600" x-on:click="setPageActions('{{ $hierarchyKey }}', '{{ $page['key'] }}', true)">Habilitar a&ccedil;&otilde;es</button>
                                                            <button type="button" class="border border-gray-400 bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600" x-on:click="setPageActions('{{ $hierarchyKey }}', '{{ $page['key'] }}', false)">Desabilitar a&ccedil;&otilde;es</button>
                                                        </div>

                                                        <div class="max-h-[500px] overflow-y-auto divide-y divide-gray-200 dark:divide-slate-700">
                                                            @if (count($page['actions']) === 0)
                                                                <div class="px-4 py-4 text-sm text-gray-500 dark:text-slate-400">Nenhuma a&ccedil;&atilde;o cadastrada para esta p&aacute;gina.</div>
                                                            @else
                                                                @foreach ($page['actions'] as $action)
                                                                    <div class="flex items-center gap-3 px-4 py-3 text-sm text-gray-800 hover:bg-gray-50 dark:text-slate-100 dark:hover:bg-slate-800">
                                                                        <input
                                                                            type="checkbox"
                                                                            name="permissions[{{ $hierarchyKey }}][{{ $action['key'] }}]"
                                                                            value="1"
                                                                            data-hierarchy="{{ $hierarchyKey }}"
                                                                            data-parent-page="{{ $page['key'] }}"
                                                                            class="h-4 w-4 rounded border-gray-400 text-gray-800 focus:ring-gray-500"
                                                                            @checked($matrix[$hierarchyKey][$action['key']] ?? false)
                                                                        >
                                                                        <span>{{ $action['label'] }}</span>
                                                                    </div>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </section>
                                </div>
                                </div>
                            </div>

                            <div>
                                <x-primary-button>Salvar permiss&otilde;es</x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
