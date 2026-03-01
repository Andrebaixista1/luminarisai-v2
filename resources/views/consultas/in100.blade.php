<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Consultas</p>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Consulta IN100
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total consultado</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">0</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pendentes</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-600">0</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Aprovados</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-600">0</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reprovados</p>
                    <p class="mt-2 text-2xl font-semibold text-rose-600">0</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Filtros de consulta</h3>
                </div>

                <form class="space-y-4 p-6">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <x-input-label for="cpf" value="CPF" />
                            <x-text-input id="cpf" type="text" class="mt-1 block w-full" placeholder="000.000.000-00" />
                        </div>
                        <div>
                            <x-input-label for="beneficio" value="Nº Benefício" />
                            <x-text-input id="beneficio" type="text" class="mt-1 block w-full" placeholder="Digite o benefício" />
                        </div>
                        <div>
                            <x-input-label for="situacao" value="Situação" />
                            <select id="situacao" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option>Todos</option>
                                <option>Pendente</option>
                                <option>Aprovado</option>
                                <option>Reprovado</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="competencia" value="Competência" />
                            <x-text-input id="competencia" type="month" class="mt-1 block w-full" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-primary-button type="button">Consultar</x-primary-button>
                        <button type="button" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Limpar
                        </button>
                        <span class="text-xs text-gray-500">Esqueleto inicial sem integração de API.</span>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Resultados</h3>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">0 registros</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">CPF</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Benefício</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Nome</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Situação</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Data consulta</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                    Nenhuma consulta realizada ainda.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

