<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\WhitelabelSetting;
use App\Support\Whitelabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WhitelabelController extends Controller
{
    public function index(Request $request): View
    {
        $settings = Whitelabel::settings();

        return view('administrative.whitelabel', [
            'settings' => $settings,
            'proprietaryUrl' => $this->proprietaryUrl($request, $settings['proprietary_slug']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'system_name' => ['required', 'string', 'max:120'],
            'proprietary_slug' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-zA-Z0-9-]+$/'],
            'logo_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'system_name.required' => 'Informe o nome do sistema.',
            'proprietary_slug.required' => 'Informe o slug do link proprietario.',
            'proprietary_slug.regex' => 'Use apenas letras, numeros e hifen no slug.',
            'logo_file.mimes' => 'O logo deve ser png, jpg, jpeg, webp ou svg.',
            'logo_file.max' => 'O logo deve ter no maximo 2MB.',
        ]);

        $row = WhitelabelSetting::query()->firstOrNew(['id' => 1]);
        $row->system_name = trim((string) $validated['system_name']);
        $row->proprietary_slug = Whitelabel::normalizeSlug((string) $validated['proprietary_slug']);

        $removeLogo = (bool) ($validated['remove_logo'] ?? false);
        if ($removeLogo && $row->logo_path) {
            Storage::disk('public')->delete($row->logo_path);
            $row->logo_path = null;
        }

        if ($request->hasFile('logo_file')) {
            if ($row->logo_path) {
                Storage::disk('public')->delete($row->logo_path);
            }

            $stored = $request->file('logo_file')->store('whitelabel-logos', 'public');
            $row->logo_path = $stored;
        }

        $row->save();
        Whitelabel::clearCache();

        return back()->with('status', 'whitelabel-updated');
    }

    public function entry(Request $request, string $slug): RedirectResponse
    {
        $configured = Whitelabel::proprietarySlug();
        if (! $configured || $configured !== Whitelabel::normalizeSlug($slug)) {
            abort(404);
        }

        return $request->user()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }

    private function proprietaryUrl(Request $request, ?string $slug): string
    {
        if (! $slug) {
            return $request->getSchemeAndHttpHost().'/w/seu-slug';
        }

        return $request->getSchemeAndHttpHost().'/w/'.$slug;
    }
}

