<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Permiss&otilde;es
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden shadow-sm sm:rounded-2xl bg-white dark:bg-slate-900">
                <div class="p-8 lg:p-10 text-gray-900 dark:text-slate-100">
                    @if (session('status') === 'permissions-updated')
                        <div class="mb-6 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                            Permiss&otilde;es atualizadas com sucesso.
                        </div>
                    @endif

                    <div class="mb-8 rounded-xl border border-gray-200 bg-gray-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/60">
                        <p class="text-sm leading-relaxed text-gray-700 dark:text-slate-300">
                            As abas s&atilde;o detectadas automaticamente pelas rotas com middleware <code>auth</code>.
                            Ao criar uma nova aba protegida, ela passa a aparecer aqui.
                        </p>
                    </div>

                    <form method="post" action="{{ route('settings.permissions.update') }}" class="space-y-10">
                        @csrf

                        <div class="space-y-6">
                            @foreach ($hierarchies as $index => $hierarchy)
                                @php
                                    $totalPermissions = count($matrix[$hierarchy['key']] ?? []);
                                    $enabledPermissions = count(array_filter($matrix[$hierarchy['key']] ?? []));
                                @endphp

                                <section x-data="{ open: {{ $index === 0 ? 'true' : 'false' }} }" class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left"
                                        x-on:click="open = !open"
                                    >
                                        <div>
                                            <h3 class="text-xl font-semibold text-gray-900 dark:text-slate-100">{{ $hierarchy['label'] }}</h3>
                                            <p class="mt-1 text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                                {{ $enabledPermissions }} de {{ $totalPermissions }} permiss&otilde;es ativas
                                            </p>
                                        </div>

                                        <div class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-300 text-gray-600 dark:border-slate-600 dark:text-slate-300">
                                            <svg class="h-5 w-5 transition-transform duration-200" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.18l3.71-3.95a.75.75 0 111.08 1.04l-4.25 4.53a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>

                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 -translate-y-1"
                                        class="border-t border-gray-100 px-6 py-6 dark:border-slate-700"
                                    >
                                        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                                            @foreach ($permissionsByGroup as $groupLabel => $permissions)
                                                <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4 dark:border-slate-700 dark:bg-slate-800">
                                                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-sky-300">{{ $groupLabel }}</h4>

                                                    <div class="space-y-2">
                                                        @foreach ($permissions as $permission)
                                                            <label class="flex items-center gap-3 rounded-lg px-2 py-2 text-sm text-gray-800 transition hover:bg-white dark:text-slate-100 dark:hover:bg-slate-700/70">
                                                                <input
                                                                    type="checkbox"
                                                                    name="permissions[{{ $hierarchy['key'] }}][{{ $permission['key'] }}]"
                                                                    value="1"
                                                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-500 dark:bg-slate-900"
                                                                    @checked($matrix[$hierarchy['key']][$permission['key']] ?? false)
                                                                >
                                                                <span class="leading-snug">{{ $permission['label'] }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </section>
                            @endforeach
                        </div>

                        <div class="pt-2">
                            <x-primary-button>Salvar permiss&otilde;es</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
