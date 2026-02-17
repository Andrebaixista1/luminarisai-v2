<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SettingsPermissionsController extends Controller
{
    private const STORAGE_FILE = 'permissions-matrix.json';

    /**
     * @var array<int, array{key: string, label: string}>
     */
    private const HIERARCHIES = [
        ['key' => 'master', 'label' => 'Master'],
        ['key' => 'administrador', 'label' => 'Administrador'],
        ['key' => 'supervisao', 'label' => "Supervis\u{00E3}o"],
        ['key' => 'operacao', 'label' => "Opera\u{00E7}\u{00E3}o"],
    ];

    public function index(): View
    {
        $permissions = $this->discoverPermissions();
        $matrix = $this->mergeMatrixWithPermissions($this->loadMatrix(), $permissions);

        return view('settings.permissions', [
            'hierarchies' => self::HIERARCHIES,
            'permissionsByGroup' => $this->groupPermissions($permissions),
            'matrix' => $matrix,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $permissions = $this->discoverPermissions();
        $input = (array) $request->input('permissions', []);
        $matrix = [];

        foreach (self::HIERARCHIES as $hierarchy) {
            $hierarchyKey = $hierarchy['key'];
            $matrix[$hierarchyKey] = [];

            foreach ($permissions as $permission) {
                $permissionKey = $permission['key'];
                $matrix[$hierarchyKey][$permissionKey] = isset($input[$hierarchyKey][$permissionKey]);
            }
        }

        $this->saveMatrix($matrix);

        return back()->with('status', 'permissions-updated');
    }

    /**
     * @return array<int, array{key: string, label: string, group: string}>
     */
    private function discoverPermissions(): array
    {
        $permissions = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if ($name === '') {
                continue;
            }

            $methods = $route->methods();
            if (! in_array('GET', $methods, true) || in_array('HEAD', $methods, true) && count($methods) === 1) {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            if ($this->isExcludedRouteName($name)) {
                continue;
            }

            $permissions[] = [
                'key' => $name,
                'label' => $this->labelFromRouteName($name),
                'group' => $this->groupFromRouteName($name),
            ];
        }

        usort($permissions, function (array $a, array $b): int {
            return [$a['group'], $a['label']] <=> [$b['group'], $b['label']];
        });

        return $permissions;
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

    private function groupFromRouteName(string $name): string
    {
        if ($name === 'dashboard') {
            return 'Principal';
        }

        if (Str::startsWith($name, 'settings.')) {
            return "Configura\u{00E7}\u{00F5}es";
        }

        return 'Outras';
    }

    private function labelFromRouteName(string $name): string
    {
        $map = [
            'dashboard' => 'Painel',
            'settings.users' => "Configura\u{00E7}\u{00F5}es -> Usu\u{00E1}rios",
            'settings.permissions' => "Configura\u{00E7}\u{00F5}es -> Permiss\u{00F5}es",
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        return Str::headline(str_replace('.', ' ', $name));
    }

    /**
     * @param array<string, array<string, bool>> $matrix
     * @param array<int, array{key: string, label: string, group: string}> $permissions
     * @return array<string, array<string, bool>>
     */
    private function mergeMatrixWithPermissions(array $matrix, array $permissions): array
    {
        $normalized = [];

        foreach (self::HIERARCHIES as $hierarchy) {
            $hierarchyKey = $hierarchy['key'];
            $normalized[$hierarchyKey] = [];

            foreach ($permissions as $permission) {
                $permissionKey = $permission['key'];
                $normalized[$hierarchyKey][$permissionKey] = (bool) ($matrix[$hierarchyKey][$permissionKey] ?? false);
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function loadMatrix(): array
    {
        $path = $this->matrixPath();
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, array<string, bool>> $matrix
     */
    private function saveMatrix(array $matrix): void
    {
        File::put(
            $this->matrixPath(),
            json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'
        );
    }

    private function matrixPath(): string
    {
        return storage_path('app/'.self::STORAGE_FILE);
    }

    /**
     * @param array<int, array{key: string, label: string, group: string}> $permissions
     * @return array<string, array<int, array{key: string, label: string, group: string}>>
     */
    private function groupPermissions(array $permissions): array
    {
        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission['group']][] = $permission;
        }

        return $grouped;
    }
}
