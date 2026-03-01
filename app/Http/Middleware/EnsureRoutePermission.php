<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoutePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName === '') {
            return $next($request);
        }

        if ($this->isExcludedRouteName($routeName)) {
            return $next($request);
        }

        $login = Str::lower(trim((string) $request->user()->name));
        if ($login === '') {
            abort(403);
        }

        try {
            $permissionsJson = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->whereRaw('LOWER(login) = ?', [$login])
                ->value('permissions_config_json');
        } catch (\Throwable) {
            abort(403);
        }

        $permissions = json_decode((string) $permissionsJson, true);
        if (! is_array($permissions)) {
            abort(403);
        }

        $candidates = $this->permissionCandidatesForRoute($routeName);
        if ($candidates === []) {
            return $next($request);
        }

        $allowed = null;
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $permissions)) {
                $allowed = (bool) $permissions[$candidate];
                break;
            }
        }

        if ($allowed !== true) {
            abort(403);
        }

        return $next($request);
    }

    private function permissionCandidatesForRoute(string $routeName): array
    {
        if ($routeName === '') {
            return [];
        }

        $candidates = [$routeName];

        // Backward compatibility for legacy permission JSON that only had page keys.
        if (Str::startsWith($routeName, 'settings.users.')) {
            $candidates[] = 'settings.users';
        }

        if (Str::startsWith($routeName, 'settings.permissions.')) {
            $candidates[] = 'settings.permissions';
        }

        if (Str::startsWith($routeName, 'consultas.')) {
            // Compatibility path for users with legacy permission JSON.
            $candidates[] = 'dashboard';
        }

        return array_values(array_unique($candidates));
    }

    private function isExcludedRouteName(string $name): bool
    {
        return Str::startsWith($name, [
            'login',
            'logout',
            'password.',
            'verification.',
            'profile.edit',
            'profile.update',
            'profile.destroy',
        ]);
    }
}
