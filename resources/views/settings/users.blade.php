<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Usuarios
        </h2>
    </x-slot>

    <div
        x-data="{
            selectedUserId: @js((string) old('user_id', '')),
            selectedUserLogin: '',
            selectedHierarchy: @js((string) old('hierarchy', 'operacao')),
            showAddPassword: false,
            showAddPasswordConfirm: false,
            hierarchyPermissionLabels: @js($hierarchyPermissionLabels),
            setHierarchyUser(id, login, hierarchy) {
                this.selectedUserId = String(id);
                this.selectedUserLogin = login;
                this.selectedHierarchy = hierarchy || 'operacao';
            },
            currentPermissionLabels() {
                return this.hierarchyPermissionLabels[this.selectedHierarchy] || [];
            }
        }"
        x-init="
            if (selectedUserId && !selectedUserLogin) { $dispatch('open-modal', 'edit-user-hierarchy'); }
            if (@js($errors->has('add_login') || $errors->has('add_password') || $errors->has('add_password_confirmation') || $errors->has('add_hierarchy'))) { $dispatch('open-modal', 'create-user'); }
        "
    >
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        @if ($canAddUsers)
                            <div class="mb-4 flex justify-end">
                                <button
                                    type="button"
                                    class="rounded border border-orange-500 bg-orange-500 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-600"
                                    x-on:click="$dispatch('open-modal', 'create-user')"
                                >
                                    Adicionar usuario
                                </button>
                            </div>
                        @endif

                        @if (session('status') === 'users-hierarchy-updated')
                            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                Hierarquia do usuario atualizada com sucesso.
                            </div>
                        @endif

                        @if (session('status') === 'users-created')
                            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                Usuario criado com sucesso.
                            </div>
                        @endif

                        @if (session('status') === 'users-deleted')
                            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                Usuario excluido com sucesso.
                            </div>
                        @endif

                        @if ($queryError)
                            <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                                {{ $queryError }}
                            </div>
                        @endif

                        @if ($errors->has('user_id') || $errors->has('hierarchy') || $errors->has('add_login') || $errors->has('add_password') || $errors->has('add_password_confirmation') || $errors->has('add_hierarchy'))
                            <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                                {{ $errors->first('add_login') ?: $errors->first('add_password') ?: $errors->first('add_password_confirmation') ?: $errors->first('add_hierarchy') ?: $errors->first('user_id') ?: $errors->first('hierarchy') }}
                            </div>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Login</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Equipe</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Hierarquia</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Criado em</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Atualizado em</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @forelse ($remoteUsers as $remoteUser)
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $remoteUser->id }}</td>
                                            <td class="px-4 py-3">
                                                <button
                                                    type="button"
                                                    class="rounded border border-gray-300 px-2 py-1 text-sm font-medium text-gray-800 hover:bg-gray-50"
                                                    data-copy-value="{{ $remoteUser->login }}"
                                                >
                                                    {{ $remoteUser->login }}
                                                </button>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $remoteUser->equipe !== '' ? $remoteUser->equipe : '-' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700">
                                                    {{ $remoteUser->hierarchy_label }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $remoteUser->created_at_label }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $remoteUser->updated_at_label }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center justify-center rounded border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"
                                                        title="Descricao"
                                                        aria-label="Descricao"
                                                        data-user-id="{{ $remoteUser->id }}"
                                                        data-user-login="{{ $remoteUser->login }}"
                                                        data-user-hierarchy="{{ $remoteUser->hierarchy }}"
                                                        x-on:click="setHierarchyUser($el.dataset.userId, $el.dataset.userLogin, $el.dataset.userHierarchy); $dispatch('open-modal', 'edit-user-hierarchy')"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M3 5h18"></path>
                                                            <path d="M3 12h18"></path>
                                                            <path d="M3 19h18"></path>
                                                        </svg>
                                                    </button>

                                                    <form method="post" action="{{ route('settings.users.delete') }}" onsubmit="return confirm('Confirma excluir este usuario?');">
                                                        @csrf
                                                        <input type="hidden" name="user_id" value="{{ $remoteUser->id }}">
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center justify-center rounded border border-red-500 p-2 text-red-500 hover:bg-red-50"
                                                            title="Excluir"
                                                            aria-label="Excluir"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M3 6h18"></path>
                                                                <path d="M8 6V4h8v2"></path>
                                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                                <path d="M10 11v6"></path>
                                                                <path d="M14 11v6"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-600" colspan="7">
                                                Nenhum usuario encontrado em <code>lumia_auth_users</code>.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <script>
                            document.addEventListener('click', async (event) => {
                                const button = event.target.closest('[data-copy-value]');
                                if (!button) return;
                                const value = button.getAttribute('data-copy-value') || '';
                                try {
                                    await navigator.clipboard.writeText(value);
                                    const original = button.textContent;
                                    button.textContent = 'Copiado!';
                                    setTimeout(() => {
                                        button.textContent = original;
                                    }, 1000);
                                } catch (_) {
                                    // Clipboard may be blocked by browser policy.
                                }
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>

        <x-modal name="edit-user-hierarchy" :show="$errors->has('user_id') || $errors->has('hierarchy')" maxWidth="2xl" focusable>
            <form method="post" action="{{ route('settings.users.update-hierarchy') }}" class="p-6 space-y-4">
                @csrf

                <h2 class="text-lg font-medium text-gray-900">
                    Definir hierarquia do usuario
                </h2>

                <p class="text-sm text-gray-600">
                    Usuario: <strong x-text="selectedUserLogin || 'Nao selecionado'"></strong>
                </p>

                <input type="hidden" name="user_id" x-model="selectedUserId">

                <div>
                    <x-input-label for="hierarchy" value="Hierarquia" />
                    <p class="mt-1 text-xs text-gray-500">
                        Hierarquias disponiveis: Master, Administrador, Supervisao e Operacao.
                    </p>
                    <select
                        id="hierarchy"
                        name="hierarchy"
                        x-model="selectedHierarchy"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        @foreach ($hierarchies as $hierarchy)
                            <option value="{{ $hierarchy['key'] }}">{{ $hierarchy['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-md p-4" style="background: #0f172a; border: 1px solid #334155;">
                    <h3 class="text-sm font-semibold" style="color: #f8fafc;">Campos com visao nesta hierarquia</h3>
                    <p class="mt-1 text-xs" style="color: #cbd5e1;">
                        Lista baseada na configuracao da tela de Permissoes para a hierarquia selecionada.
                    </p>
                    <p class="mt-2 text-xs" style="color: #93c5fd;">
                        Total permitido: <span x-text="currentPermissionLabels().length"></span>
                    </p>
                    <template x-if="currentPermissionLabels().length === 0">
                        <p class="mt-2 text-sm" style="color: #e2e8f0;">Nenhuma permissao ativa para esta hierarquia.</p>
                    </template>
                    <ul class="mt-2 list-disc pl-5 text-sm" style="color: #e2e8f0;" x-show="currentPermissionLabels().length > 0">
                        <template x-for="permissionLabel in currentPermissionLabels()" :key="permissionLabel">
                            <li class="py-0.5" x-text="permissionLabel"></li>
                        </template>
                    </ul>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')" type="button">
                        Cancelar
                    </x-secondary-button>
                    <x-primary-button type="submit">
                        Salvar hierarquia
                    </x-primary-button>
                </div>
            </form>
        </x-modal>

        <x-modal name="create-user" :show="$errors->has('add_login') || $errors->has('add_password') || $errors->has('add_password_confirmation') || $errors->has('add_hierarchy')" maxWidth="2xl" focusable>
            <form method="post" action="{{ route('settings.users.store') }}" class="p-6 space-y-4">
                @csrf

                <h2 class="text-lg font-medium text-gray-900">
                    Adicionar novo usuario
                </h2>

                <div>
                    <x-input-label for="add_login" value="Login" />
                    <x-text-input id="add_login" name="add_login" type="text" class="mt-1 block w-full" :value="old('add_login')" autocomplete="off" />
                    <x-input-error :messages="$errors->get('add_login')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="add_password" value="Senha" />
                    <div class="mt-1" style="position: relative;">
                        <x-text-input id="add_password" name="add_password" x-bind:type="showAddPassword ? 'text' : 'password'" class="block w-full" style="padding-right: 2.75rem;" autocomplete="new-password" />
                        <button
                            type="button"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);"
                            class="text-gray-500 hover:text-gray-700"
                            x-on:click="showAddPassword = !showAddPassword"
                            :aria-label="showAddPassword ? 'Ocultar senha' : 'Mostrar senha'"
                            :title="showAddPassword ? 'Ocultar senha' : 'Mostrar senha'"
                        >
                            <svg x-show="!showAddPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg x-show="showAddPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                                <path d="M1 1l22 22"></path>
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>
                                <path d="M9.53 9.53A3.5 3.5 0 0 0 12 15.5a3.5 3.5 0 0 0 2.47-.97"></path>
                                <path d="M21.94 12s-1.4-2.8-4.56-5.06A10.94 10.94 0 0 0 12 4c-.84 0-1.65.09-2.44.26"></path>
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('add_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="add_password_confirmation" value="Confirmacao senha" />
                    <div class="mt-1" style="position: relative;">
                        <x-text-input id="add_password_confirmation" name="add_password_confirmation" x-bind:type="showAddPasswordConfirm ? 'text' : 'password'" class="block w-full" style="padding-right: 2.75rem;" autocomplete="new-password" />
                        <button
                            type="button"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);"
                            class="text-gray-500 hover:text-gray-700"
                            x-on:click="showAddPasswordConfirm = !showAddPasswordConfirm"
                            :aria-label="showAddPasswordConfirm ? 'Ocultar senha' : 'Mostrar senha'"
                            :title="showAddPasswordConfirm ? 'Ocultar senha' : 'Mostrar senha'"
                        >
                            <svg x-show="!showAddPasswordConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg x-show="showAddPasswordConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                                <path d="M1 1l22 22"></path>
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>
                                <path d="M9.53 9.53A3.5 3.5 0 0 0 12 15.5a3.5 3.5 0 0 0 2.47-.97"></path>
                                <path d="M21.94 12s-1.4-2.8-4.56-5.06A10.94 10.94 0 0 0 12 4c-.84 0-1.65.09-2.44.26"></path>
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('add_password_confirmation')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="add_hierarchy" value="Hierarquia" />
                    <select
                        id="add_hierarchy"
                        name="add_hierarchy"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        @foreach ($hierarchies as $hierarchy)
                            <option value="{{ $hierarchy['key'] }}" @selected(old('add_hierarchy', 'operacao') === $hierarchy['key'])>{{ $hierarchy['label'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('add_hierarchy')" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')" type="button">
                        Cancelar
                    </x-secondary-button>
                    <x-primary-button type="submit">
                        Adicionar
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
