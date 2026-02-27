<?php

namespace App\Support;

use App\Models\WhitelabelSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Whitelabel
{
    private const CACHE_KEY = 'whitelabel.settings.v1';

    /**
     * @return array{system_name: string, logo_path: ?string, logo_url: ?string, proprietary_slug: ?string}
     */
    public static function settings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $fallback = [
                'system_name' => 'Lumi.A',
                'logo_path' => null,
                'logo_url' => null,
                'proprietary_slug' => null,
            ];

            try {
                $row = WhitelabelSetting::query()->first();
            } catch (\Throwable) {
                return $fallback;
            }

            if (! $row) {
                return $fallback;
            }

            $logoPath = $row->logo_path ?: null;
            return [
                'system_name' => trim((string) $row->system_name) !== '' ? (string) $row->system_name : $fallback['system_name'],
                'logo_path' => $logoPath,
                'logo_url' => $logoPath ? Storage::url($logoPath) : null,
                'proprietary_slug' => self::normalizeSlug((string) ($row->proprietary_slug ?? '')),
            ];
        });
    }

    public static function systemName(): string
    {
        return self::settings()['system_name'];
    }

    public static function logoUrl(): ?string
    {
        return self::settings()['logo_url'];
    }

    public static function proprietarySlug(): ?string
    {
        return self::settings()['proprietary_slug'];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function normalizeSlug(string $slug): ?string
    {
        $normalized = Str::of($slug)->trim()->lower()->replace(' ', '-');
        $normalized = preg_replace('/[^a-z0-9-]/', '', (string) $normalized) ?? '';
        $normalized = preg_replace('/-+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : null;
    }
}

