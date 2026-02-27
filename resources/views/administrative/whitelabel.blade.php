<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Whitelabel
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden border border-gray-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="p-6 text-gray-900 dark:text-slate-100">
                    @if (session('status') === 'whitelabel-updated')
                        <div class="mb-6 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            Whitelabel atualizado com sucesso.
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="post" action="{{ route('administrative.whitelabel.update') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="system_name" value="Nome do sistema" />
                            <x-text-input id="system_name" name="system_name" type="text" class="mt-1 block w-full" :value="old('system_name', $settings['system_name'])" required />
                        </div>

                        <div>
                            <x-input-label for="proprietary_slug" value="Slug do link proprietario" />
                            <x-text-input id="proprietary_slug" name="proprietary_slug" type="text" class="mt-1 block w-full" :value="old('proprietary_slug', $settings['proprietary_slug'] ?? '')" placeholder="ex: minha-marca" required />
                            <p class="mt-2 text-xs text-gray-500">
                                Link gerado: <a href="{{ $proprietaryUrl }}" class="underline hover:no-underline">{{ $proprietaryUrl }}</a>
                            </p>
                        </div>

                        <div>
                            <x-input-label for="logo_file" value="Logo (png, jpg, webp ou svg)" />
                            <input id="logo_file" name="logo_file" type="file" class="mt-1 block w-full rounded-md border-gray-300 text-sm" accept=".png,.jpg,.jpeg,.webp,.svg">

                            @if ($settings['logo_url'])
                                <div class="mt-3 flex items-center gap-3">
                                    <img src="{{ $settings['logo_url'] }}" alt="Logo atual" class="h-14 w-14 rounded border border-gray-300 object-contain bg-white p-1">
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="remove_logo" value="1">
                                        Remover logo atual
                                    </label>
                                </div>
                            @endif
                        </div>

                        <div class="rounded border border-gray-300 bg-gray-50 p-4">
                            <p class="text-sm font-semibold text-gray-800">Preview</p>
                            <div class="mt-3 flex items-center gap-3">
                                @if ($settings['logo_url'])
                                    <img src="{{ $settings['logo_url'] }}" alt="Preview logo" class="h-10 w-10 rounded border border-gray-300 object-contain bg-white p-1">
                                @else
                                    <x-application-logo class="h-10 w-10" />
                                @endif
                                <span class="text-base font-semibold text-gray-900">{{ old('system_name', $settings['system_name']) }}</span>
                            </div>
                        </div>

                        <div>
                            <x-primary-button>Salvar alteracoes</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

