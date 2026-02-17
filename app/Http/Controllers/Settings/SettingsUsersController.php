<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SettingsUsersController extends Controller
{
    private const SESSION_UNLOCK_KEY = 'settings_users_password_unlocked_until';
    private const MATRIX_STORAGE_FILE = 'permissions-matrix.json';

    /**
     * @var array<int, array{key: string, label: string}>
     */
    private const HIERARCHIES = [
        ['key' => 'master', 'label' => 'Master'],
        ['key' => 'administrador', 'label' => 'Administrador'],
        ['key' => 'supervisao', 'label' => 'Supervisao'],
        ['key' => 'operacao', 'label' => 'Operacao'],
    ];

    public function index(Request $request): View
    {
        $queryError = null;
        $remoteUsers = [];

        $permissions = $this->discoverPermissions();
        $matrix = $this->mergeMatrixWithPermissions($this->loadMatrix(), $permissions);
        $hierarchyPermissionLabels = $this->buildHierarchyPermissionLabels($matrix, $permissions);
        $hierarchyLabels = $this->hierarchyLabels();
        $hasEquipeColumn = $this->hasEquipeColumn();

        try {
            $selectColumns = ['id', 'login', 'password_sha256', 'role', 'permissions_config_json', 'created_at', 'updated_at'];
            if ($hasEquipeColumn) {
                $selectColumns[] = 'equipe';
            }

            $rows = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->select($selectColumns)
                ->orderBy('login')
                ->get();

            foreach ($rows as $row) {
                $normalizedHash = $this->normalizeSha256($row->password_sha256);
                $row->password_sha256 = $normalizedHash ?? (string) $row->password_sha256;
                $row->equipe = trim((string) ($row->equipe ?? ''));
                if ((int) $row->id === 1 && $row->equipe === '') {
                    $row->equipe = 'CEO';
                }
                $row->created_at_label = $this->formatDateTimeLabel($row->created_at);
                $row->updated_at_label = $this->formatDateTimeLabel($row->updated_at);
                $row->hierarchy = $this->resolveUserHierarchy(
                    $row->role,
                    (string) ($row->permissions_config_json ?? ''),
                    $matrix
                );
                $row->hierarchy_label = $hierarchyLabels[$row->hierarchy] ?? Str::headline($row->hierarchy);
                $remoteUsers[] = $row;
            }
        } catch (\Throwable) {
            $queryError = 'Nao foi possivel carregar os dados de usuarios no momento.';
        }

        return view('settings.users', [
            'queryError' => $queryError,
            'remoteUsers' => $remoteUsers,
            'hierarchies' => self::HIERARCHIES,
            'hierarchyPermissionLabels' => $hierarchyPermissionLabels,
            'hasEquipeColumn' => $hasEquipeColumn,
            'canAddUsers' => $this->canCurrentUserAddUsers($request),
        ]);
    }

    public function unlockPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'master_password' => ['required', 'string'],
        ], [
            'master_password.required' => 'Informe a senha atual do master.',
        ]);

        try {
            $masterUser = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->select(['id', 'password_sha256'])
                ->where('id', 1)
                ->first();
        } catch (\Throwable) {
            return back()->withErrors([
                'master_password' => 'Falha ao validar a senha master.',
            ]);
        }

        $candidateSha256 = hash('sha256', (string) $validated['master_password']);
        $masterSha256 = $this->normalizeSha256($masterUser?->password_sha256);
        if (! $masterSha256 || ! hash_equals($masterSha256, $candidateSha256)) {
            return back()->withErrors([
                'master_password' => 'Senha master invalida.',
            ]);
        }

        $request->session()->put(self::SESSION_UNLOCK_KEY, now()->addMinutes(2)->timestamp);

        return back()->with('status', 'users-password-unlocked');
    }

    public function updateHierarchy(Request $request): RedirectResponse
    {
        $hierarchyKeys = array_map(static fn (array $h): string => $h['key'], self::HIERARCHIES);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'hierarchy' => ['required', 'string', 'in:'.implode(',', $hierarchyKeys)],
        ], [
            'user_id.required' => 'Selecione um usuario.',
            'hierarchy.required' => 'Selecione uma hierarquia.',
            'hierarchy.in' => 'Hierarquia invalida.',
        ]);

        $permissions = $this->discoverPermissions();
        $matrix = $this->mergeMatrixWithPermissions($this->loadMatrix(), $permissions);
        $hierarchy = (string) $validated['hierarchy'];
        $permissionsForHierarchy = $matrix[$hierarchy] ?? [];
        $permissionsJson = json_encode($permissionsForHierarchy, JSON_UNESCAPED_SLASHES);

        if ($permissionsJson === false) {
            return back()->withErrors([
                'hierarchy' => 'Nao foi possivel gerar as permissoes da hierarquia.',
            ])->withInput();
        }

        try {
            $updatedRows = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->where('id', (int) $validated['user_id'])
                ->update([
                    'role' => $hierarchy,
                    'permissions_config_json' => $permissionsJson,
                ]);
        } catch (\Throwable) {
            return back()->withErrors([
                'hierarchy' => 'Falha ao atualizar a hierarquia do usuario.',
            ])->withInput();
        }

        if ($updatedRows === 0) {
            return back()->withErrors([
                'user_id' => 'Usuario nao encontrado para atualizacao.',
            ])->withInput();
        }

        return back()->with('status', 'users-hierarchy-updated');
    }

    public function deleteUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ], [
            'user_id.required' => 'Selecione um usuario para excluir.',
        ]);

        $userId = (int) $validated['user_id'];
        if ($userId === 1) {
            return back()->withErrors([
                'user_id' => 'O usuario master (ID 1) nao pode ser excluido.',
            ]);
        }

        try {
            $deletedRows = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->where('id', $userId)
                ->delete();
        } catch (\Throwable) {
            return back()->withErrors([
                'user_id' => 'Falha ao excluir o usuario.',
            ]);
        }

        if ($deletedRows === 0) {
            return back()->withErrors([
                'user_id' => 'Usuario nao encontrado para exclusao.',
            ]);
        }

        return back()->with('status', 'users-deleted');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        if (! $this->canCurrentUserAddUsers($request)) {
            return back()->withErrors([
                'add_login' => 'Apenas o usuario master (ID 1) pode adicionar novos usuarios.',
            ])->withInput();
        }

        $hierarchyKeys = array_map(static fn (array $h): string => $h['key'], self::HIERARCHIES);

        $validated = $request->validate([
            'add_login' => ['required', 'string', 'min:3', 'max:120'],
            'add_password' => ['required', 'string', 'min:6', 'confirmed'],
            'add_hierarchy' => ['required', 'string', 'in:'.implode(',', $hierarchyKeys)],
        ], [
            'add_login.required' => 'Informe o login.',
            'add_password.required' => 'Informe a senha.',
            'add_password.min' => 'A senha deve ter no minimo 6 caracteres.',
            'add_password.confirmed' => 'A confirmacao da senha nao confere.',
            'add_hierarchy.required' => 'Selecione a hierarquia.',
            'add_hierarchy.in' => 'Hierarquia invalida.',
        ]);

        $login = trim((string) $validated['add_login']);
        $loginLower = Str::lower($login);

        try {
            $alreadyExists = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->whereRaw('LOWER(login) = ?', [$loginLower])
                ->exists();
        } catch (\Throwable) {
            return back()->withErrors([
                'add_login' => 'Falha ao validar login no banco.',
            ])->withInput();
        }

        if ($alreadyExists) {
            return back()->withErrors([
                'add_login' => 'Ja existe um usuario com este login.',
            ])->withInput();
        }

        $permissions = $this->discoverPermissions();
        $matrix = $this->mergeMatrixWithPermissions($this->loadMatrix(), $permissions);
        $hierarchy = (string) $validated['add_hierarchy'];
        $permissionsForHierarchy = $matrix[$hierarchy] ?? [];
        $permissionsJson = json_encode($permissionsForHierarchy, JSON_UNESCAPED_SLASHES);
        if ($permissionsJson === false) {
            return back()->withErrors([
                'add_hierarchy' => 'Nao foi possivel gerar permissoes para a hierarquia.',
            ])->withInput();
        }

        $passwordSha256 = hash('sha256', (string) $validated['add_password']);
        $now = now();

        try {
            DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->insert([
                    'login' => $login,
                    'password_sha256' => $passwordSha256,
                    'role' => $hierarchy,
                    'permissions_config_json' => $permissionsJson,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'equipe' => null,
                ]);
        } catch (\Throwable) {
            return back()->withErrors([
                'add_login' => 'Falha ao criar usuario no banco.',
            ])->withInput();
        }

        return back()->with('status', 'users-created');
    }

    private function canCurrentUserAddUsers(Request $request): bool
    {
        $login = Str::lower(trim((string) $request->user()?->name));
        if ($login === '') {
            return false;
        }

        try {
            $remoteUserId = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->whereRaw('LOWER(login) = ?', [$login])
                ->value('id');
        } catch (\Throwable) {
            return false;
        }

        return (int) $remoteUserId === 1;
    }

    private function hasEquipeColumn(): bool
    {
        try {
            return DB::connection('lumia_sqlsrv')
                ->getSchemaBuilder()
                ->hasColumn('lumia_auth_users', 'equipe');
        } catch (\Throwable) {
            return false;
        }
    }

    private function formatDateTimeLabel(mixed $value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)
                ->timezone('America/Sao_Paulo')
                ->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function normalizeSha256(mixed $value): ?string
    {
        $hash = Str::lower(trim((string) $value));
        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null;
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
            return 'Configuracoes';
        }

        return 'Outras';
    }

    private function labelFromRouteName(string $name): string
    {
        $map = [
            'dashboard' => 'Painel',
            'settings.users' => 'Configuracoes -> Usuarios',
            'settings.permissions' => 'Configuracoes -> Permissoes',
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
        $path = storage_path('app/'.self::MATRIX_STORAGE_FILE);
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, array<string, bool>> $matrix
     * @param array<int, array{key: string, label: string, group: string}> $permissions
     * @return array<string, array<int, string>>
     */
    private function buildHierarchyPermissionLabels(array $matrix, array $permissions): array
    {
        $labelsByKey = [];
        foreach ($permissions as $permission) {
            $labelsByKey[$permission['key']] = $permission['label'];
        }

        $result = [];
        foreach (self::HIERARCHIES as $hierarchy) {
            $hierarchyKey = $hierarchy['key'];
            $result[$hierarchyKey] = [];

            foreach (($matrix[$hierarchyKey] ?? []) as $permissionKey => $enabled) {
                if (! $enabled) {
                    continue;
                }

                $result[$hierarchyKey][] = $labelsByKey[$permissionKey] ?? $permissionKey;
            }

            sort($result[$hierarchyKey]);
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function hierarchyLabels(): array
    {
        $labels = [];
        foreach (self::HIERARCHIES as $hierarchy) {
            $labels[$hierarchy['key']] = $hierarchy['label'];
        }

        return $labels;
    }

    /**
     * @param array<string, array<string, bool>> $matrix
     */
    private function resolveUserHierarchy(mixed $role, string $permissionsConfigJson, array $matrix): string
    {
        $roleKey = Str::lower(trim((string) $role));
        if ($roleKey !== '' && isset($matrix[$roleKey])) {
            return $roleKey;
        }

        $config = json_decode($permissionsConfigJson, true);
        if (! is_array($config)) {
            return 'operacao';
        }

        foreach (self::HIERARCHIES as $hierarchy) {
            $hierarchyKey = $hierarchy['key'];
            $candidate = $matrix[$hierarchyKey] ?? [];
            $same = true;
            foreach ($candidate as $permissionKey => $enabled) {
                if ((bool) ($config[$permissionKey] ?? false) !== (bool) $enabled) {
                    $same = false;
                    break;
                }
            }

            if ($same) {
                return $hierarchyKey;
            }
        }

        return 'operacao';
    }
}
