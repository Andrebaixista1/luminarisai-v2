@php
    $passwordError = $errors->updatePassword->first('current_password')
        ?: $errors->updatePassword->first('password')
        ?: $errors->updatePassword->first('password_confirmation');
@endphp

<section
    x-data="{
        showCurrent: false,
        showNew: false,
        showConfirm: false,
        showToast: {{ session('status') === 'password-updated' || !empty($passwordError) ? 'true' : 'false' }},
        toastType: '{{ session('status') === 'password-updated' ? 'success' : (!empty($passwordError) ? 'error' : '') }}'
    }"
    x-init="if (showToast) setTimeout(() => showToast = false, 3500)"
>
    <div
        x-show="showToast"
        x-transition
        class="fixed top-5 right-5 z-50 max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg"
        :class="toastType === 'success'
            ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
            : 'border-red-300 bg-red-50 text-red-800'"
        style="display: none;"
    >
        @if (session('status') === 'password-updated')
            Senha atualizada com sucesso.
        @elseif (!empty($passwordError))
            {{ $passwordError }}
        @endif
    </div>

    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Alterar senha
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Garanta que sua conta use uma senha longa e aleatoria para manter a seguranca.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Senha atual" />
            <div class="relative mt-1" style="position: relative;">
                <x-text-input id="update_password_current_password" name="current_password" x-bind:type="showCurrent ? 'text' : 'password'" class="block w-full pe-12" style="padding-right: 2.75rem;" autocomplete="current-password" />
                <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); z-index: 1;"
                    x-on:click="showCurrent = !showCurrent"
                    :aria-label="showCurrent ? 'Ocultar senha' : 'Mostrar senha'"
                    :title="showCurrent ? 'Ocultar senha' : 'Mostrar senha'"
                >
                    <svg x-show="!showCurrent" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg x-show="showCurrent" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                        <path d="M1 1l22 22"></path>
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>
                        <path d="M9.53 9.53A3.5 3.5 0 0 0 12 15.5a3.5 3.5 0 0 0 2.47-.97"></path>
                        <path d="M21.94 12s-1.4-2.8-4.56-5.06A10.94 10.94 0 0 0 12 4c-.84 0-1.65.09-2.44.26"></path>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Nova senha" />
            <div class="relative mt-1" style="position: relative;">
                <x-text-input id="update_password_password" name="password" x-bind:type="showNew ? 'text' : 'password'" class="block w-full pe-12" style="padding-right: 2.75rem;" autocomplete="new-password" />
                <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); z-index: 1;"
                    x-on:click="showNew = !showNew"
                    :aria-label="showNew ? 'Ocultar senha' : 'Mostrar senha'"
                    :title="showNew ? 'Ocultar senha' : 'Mostrar senha'"
                >
                    <svg x-show="!showNew" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg x-show="showNew" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                        <path d="M1 1l22 22"></path>
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>
                        <path d="M9.53 9.53A3.5 3.5 0 0 0 12 15.5a3.5 3.5 0 0 0 2.47-.97"></path>
                        <path d="M21.94 12s-1.4-2.8-4.56-5.06A10.94 10.94 0 0 0 12 4c-.84 0-1.65.09-2.44.26"></path>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Confirmar senha" />
            <div class="relative mt-1" style="position: relative;">
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" x-bind:type="showConfirm ? 'text' : 'password'" class="block w-full pe-12" style="padding-right: 2.75rem;" autocomplete="new-password" />
                <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); z-index: 1;"
                    x-on:click="showConfirm = !showConfirm"
                    :aria-label="showConfirm ? 'Ocultar senha' : 'Mostrar senha'"
                    :title="showConfirm ? 'Ocultar senha' : 'Mostrar senha'"
                >
                    <svg x-show="!showConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg x-show="showConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                        <path d="M1 1l22 22"></path>
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>
                        <path d="M9.53 9.53A3.5 3.5 0 0 0 12 15.5a3.5 3.5 0 0 0 2.47-.97"></path>
                        <path d="M21.94 12s-1.4-2.8-4.56-5.06A10.94 10.94 0 0 0 12 4c-.84 0-1.65.09-2.44.26"></path>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Salvar</x-primary-button>
        </div>
    </form>
</section>
