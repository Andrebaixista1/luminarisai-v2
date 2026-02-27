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
            'permissionTree' => $this->buildPermissionTree($permissions),
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
     * @return array<int, array{key: string, label: string, group: string, type: string, method: string}>
     */
    private function discoverPermissions(): array
    {
        $permissions = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if ($name === '') {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            if ($this->isExcludedRouteName($name)) {
                continue;
            }

            $method = $this->primaryRouteMethod($route->methods());
            if ($method === null) {
                continue;
            }

            $permissions[] = [
                'key' => $name,
                'label' => $this->labelFromRouteName($name, $method),
                'group' => $this->groupFromRouteName($name),
                'type' => $method === 'GET' ? 'page' : 'action',
                'method' => $method,
            ];
        }

        usort($permissions, function (array $a, array $b): int {
            $typeOrderA = $a['type'] === 'page' ? 0 : 1;
            $typeOrderB = $b['type'] === 'page' ? 0 : 1;
            return [$a['group'], $typeOrderA, $a['label']] <=> [$b['group'], $typeOrderB, $b['label']];
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

        if (Str::startsWith($name, 'administrative.')) {
            return 'Administrativo';
        }

        return 'Outras';
    }

    private function labelFromRouteName(string $name, string $method): string
    {
        $map = [
            'dashboard' => 'Painel',
            'settings.users' => "Usu\u{00E1}rios",
            'settings.users.store' => "Criar usu\u{00E1}rio",
            'settings.users.unlock-password' => "Liberar senha",
            'settings.users.update-hierarchy' => "Alterar hierarquia",
            'settings.users.update' => "Editar usu\u{00E1}rio",
            'settings.users.delete' => "Excluir usu\u{00E1}rio",
            'settings.permissions' => "Permiss\u{00F5}es",
            'settings.permissions.update' => "Salvar altera\u{00E7}\u{00F5}es",
            'administrative.whitelabel' => 'Whitelabel',
            'administrative.whitelabel.update' => 'Salvar Whitelabel',
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        return Str::headline(str_replace('.', ' ', $name));
    }

    private function primaryRouteMethod(array $methods): ?string
    {
        foreach ($methods as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            return $method;
        }

        return null;
    }

    /**
     * @param array<string, array<string, bool>> $matrix
     * @param array<int, array{key: string, label: string, group: string, type: string, method: string}> $permissions
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
                $normalized[$hierarchyKey][$permissionKey] = array_key_exists($permissionKey, $matrix[$hierarchyKey] ?? [])
                    ? (bool) $matrix[$hierarchyKey][$permissionKey]
                    : $this->defaultPermissionValue($hierarchyKey, $permissionKey);
            }
        }

        return $normalized;
    }

    private function defaultPermissionValue(string $hierarchyKey, string $permissionKey): bool
    {
        if ($hierarchyKey === 'master') {
            return true;
        }

        if ($permissionKey === 'dashboard') {
            return true;
        }

        if ($permissionKey === 'settings.users') {
            return in_array($hierarchyKey, ['administrador', 'supervisao'], true);
        }

        return false;
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
     * @param array<int, array{key: string, label: string, group: string, type: string, method: string}> $permissions
     * @return array<int, array{key: string, label: string, group: string, actions: array<int, array{key: string, label: string, group: string, type: string, method: string}>}>
     */
    private function buildPermissionTree(array $permissions): array
    {
        $pages = [];
        $actions = [];

        foreach ($permissions as $permission) {
            if ($permission['type'] === 'page') {
                $pages[] = $permission;
            } else {
                $actions[] = $permission;
            }
        }

        $tree = [];
        foreach ($pages as $page) {
            $tree[$page['key']] = [
                'key' => $page['key'],
                'label' => $page['label'],
                'group' => $page['group'],
                'actions' => [],
            ];
        }

        foreach ($actions as $action) {
            $pageKey = $this->resolveActionParentPageKey($action['key'], $tree);
            if ($pageKey === null) {
                continue;
            }

            $tree[$pageKey]['actions'][] = $action;
        }

        foreach ($tree as &$node) {
            usort($node['actions'], fn (array $a, array $b): int => $a['label'] <=> $b['label']);
        }
        unset($node);

        return array_values($tree);
    }

    /**
     * @param array<string, array{key: string, label: string, group: string, actions: array<int, array{key: string, label: string, group: string, type: string, method: string}>}> $tree
     */
    private function resolveActionParentPageKey(string $actionKey, array $tree): ?string
    {
        $matchedPageKey = null;

        foreach (array_keys($tree) as $pageKey) {
            if (! Str::startsWith($actionKey, $pageKey.'.')) {
                continue;
            }

            if ($matchedPageKey === null || strlen($pageKey) > strlen($matchedPageKey)) {
                $matchedPageKey = $pageKey;
            }
        }

        return $matchedPageKey;
    }
}
