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
        $existingTeams = [];
        $currentRemoteLogin = Str::lower(trim((string) $request->user()?->name));
        $currentRemoteHierarchy = null;
        $currentRemoteEquipe = null;
        $isCurrentRemoteMaster = $this->currentRemoteUserId($request) === 1;
        $currentPermissions = $this->currentRemotePermissions($request);
        $canAddUsers = $this->hasCurrentUserPermission($currentPermissions, 'settings.users.store');
        $canEditUsers = $this->hasCurrentUserPermission($currentPermissions, 'settings.users.update');
        $canEditHierarchy = $this->hasCurrentUserPermission($currentPermissions, 'settings.users.update-hierarchy');
        $canDeleteUsers = $this->hasCurrentUserPermission($currentPermissions, 'settings.users.delete');

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
                if ($row->equipe !== '') {
                    $existingTeams[$row->equipe] = true;
                }
                $row->created_at_label = $this->formatDateTimeLabel($row->created_at);
                $row->updated_at_label = $this->formatDateTimeLabel($row->updated_at);
                $row->hierarchy = $this->resolveUserHierarchy(
                    $row->role,
                    (string) ($row->permissions_config_json ?? ''),
                    $matrix
                );
                $row->hierarchy_label = $hierarchyLabels[$row->hierarchy] ?? Str::headline($row->hierarchy);
                $row->is_current_user = $currentRemoteLogin !== '' && Str::lower((string) $row->login) === $currentRemoteLogin;
                if ($row->is_current_user) {
                    $currentRemoteHierarchy = $row->hierarchy;
                    $currentRemoteEquipe = $row->equipe;
                }
                $remoteUsers[] = $row;
            }
        } catch (\Throwable) {
            $queryError = 'Nao foi possivel carregar os dados de usuarios no momento.';
        }

        if ($currentRemoteHierarchy === 'supervisao') {
            $remoteUsers = array_values(array_filter($remoteUsers, function ($row) use ($currentRemoteEquipe) {
                return $row->equipe === $currentRemoteEquipe;
            }));
        }

        return view('settings.users', [
            'queryError' => $queryError,
            'remoteUsers' => $remoteUsers,
            'existingTeams' => array_values(array_keys($existingTeams)),
            'hierarchies' => self::HIERARCHIES,
            'hierarchyPermissionLabels' => $hierarchyPermissionLabels,
            'hasEquipeColumn' => $hasEquipeColumn,
            'canAddUsers' => $canAddUsers,
            'canEditUsers' => $canEditUsers,
            'canEditHierarchy' => $canEditHierarchy,
            'canDeleteUsers' => $canDeleteUsers,
            'isCurrentRemoteMaster' => $isCurrentRemoteMaster,
        ]);
    }

    public function unlockPassword(Request $request): RedirectResponse
    {
        if (! $this->hasCurrentUserPermission($this->currentRemotePermissions($request), 'settings.users.unlock-password')) {
            return back()->withErrors([
                'master_password' => 'Sem permissao para liberar senha.',
            ]);
        }

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
        $currentRemoteUserId = $this->currentRemoteUserId($request);
        if (! $this->hasCurrentUserPermission($this->currentRemotePermissions($request), 'settings.users.update-hierarchy')) {
            return back()->withErrors([
                'hierarchy' => 'Sem permissao para alterar hierarquia.',
            ])->withInput();
        }

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
        if ($hierarchy === 'master' && $currentRemoteUserId !== 1) {
            return back()->withErrors([
                'hierarchy' => 'Apenas o usuario master (ID 1) pode atribuir hierarquia Master.',
            ])->withInput();
        }

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
        if (! $this->hasCurrentUserPermission($this->currentRemotePermissions($request), 'settings.users.delete')) {
            return $this->redirectBackWithDeleteErrors($request, [
                'user_id' => 'Sem permissao para excluir usuarios.',
            ]);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'current_password' => ['required', 'string'],
        ], [
            'user_id.required' => 'Selecione um usuario para excluir.',
            'current_password.required' => 'Informe a senha atual para confirmar a exclusao.',
        ]);

        $userId = (int) $validated['user_id'];
        if ($userId === 1) {
            return $this->redirectBackWithDeleteErrors($request, [
                'user_id' => 'O usuario master (ID 1) nao pode ser excluido.',
            ]);
        }

        $currentRemoteUserId = $this->currentRemoteUserId($request);
        if ($currentRemoteUserId !== null && $currentRemoteUserId === $userId) {
            return $this->redirectBackWithDeleteErrors($request, [
                'user_id' => 'Voce nao pode excluir o seu proprio login.',
            ]);
        }

        if ($currentRemoteUserId === null) {
            return $this->redirectBackWithDeleteErrors($request, [
                'current_password' => 'Falha ao validar o usuario atual para excluir.',
            ]);
        }

        try {
            $currentRemoteUser = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->select(['password_sha256'])
                ->where('id', $currentRemoteUserId)
                ->first();
        } catch (\Throwable) {
            return $this->redirectBackWithDeleteErrors($request, [
                'current_password' => 'Falha ao validar a senha atual.',
            ]);
        }

        $currentPasswordSha256 = hash('sha256', (string) $validated['current_password']);
        $storedHash = $this->normalizeSha256($currentRemoteUser?->password_sha256);
        if (! $storedHash) {
            $storedHash = (string) ($currentRemoteUser?->password_sha256 ?? '');
        }

        if (! $currentRemoteUser || ! hash_equals($storedHash, $currentPasswordSha256)) {
            return $this->redirectBackWithDeleteErrors($request, [
                'current_password' => 'Senha atual incorreta.',
            ]);
        }

        try {
            $deletedRows = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->where('id', $userId)
                ->delete();
        } catch (\Throwable) {
            return $this->redirectBackWithDeleteErrors($request, [
                'user_id' => 'Falha ao excluir o usuario.',
            ]);
        }

        if ($deletedRows === 0) {
            return $this->redirectBackWithDeleteErrors($request, [
                'user_id' => 'Usuario nao encontrado para exclusao.',
            ]);
        }

        return back()->with('status', 'users-deleted');
    }

    public function updateUser(Request $request): RedirectResponse
    {
        $currentPermissions = $this->currentRemotePermissions($request);
        if (! $this->hasCurrentUserPermission($currentPermissions, 'settings.users.update')) {
            return back()->withErrors([
                'edit_login' => 'Sem permissao para editar usuarios.',
            ])->withInput();
        }

        $currentRemoteUserId = $this->currentRemoteUserId($request);
        $hierarchyKeys = array_map(static fn (array $h): string => $h['key'], self::HIERARCHIES);
        $canChangeHierarchy = $this->hasCurrentUserPermission($currentPermissions, 'settings.users.update-hierarchy');

        $rules = [
            'user_id' => ['required', 'integer'],
            'edit_login' => ['required', 'string', 'min:3', 'max:120'],
            'edit_password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'edit_equipe' => ['nullable', 'string', 'max:120'],
        ];
        $messages = [
            'user_id.required' => 'Selecione um usuario para editar.',
            'edit_login.required' => 'Informe o login.',
            'edit_login.min' => 'O login deve ter no minimo 3 caracteres.',
            'edit_login.max' => 'O login deve ter no maximo 120 caracteres.',
            'edit_password.min' => 'A senha deve ter no minimo 6 caracteres.',
            'edit_password.confirmed' => 'A confirmacao da senha nao confere.',
            'edit_equipe.max' => 'O nome da equipe deve ter no maximo 120 caracteres.',
        ];

        if ($canChangeHierarchy) {
            $rules['hierarchy'] = ['required', 'string', 'in:'.implode(',', $hierarchyKeys)];
            $messages['hierarchy.required'] = 'Selecione a hierarquia.';
            $messages['hierarchy.in'] = 'Hierarquia invalida.';
        }

        $validated = $request->validate($rules, $messages);

        $userId = (int) $validated['user_id'];
        $login = trim((string) $validated['edit_login']);
        $loginLower = Str::lower($login);

        try {
            $loginConflict = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->whereRaw('LOWER(login) = ?', [$loginLower])
                ->where('id', '<>', $userId)
                ->exists();
        } catch (\Throwable) {
            return back()->withErrors([
                'edit_login' => 'Falha ao validar login no banco.',
            ])->withInput();
        }

        if ($loginConflict) {
            return back()->withErrors([
                'edit_login' => 'Ja existe um usuario com este login.',
            ])->withInput();
        }

        $update = [
            'login' => $login,
            'updated_at' => now(),
        ];
        if (! empty($validated['edit_password'])) {
            $update['password_sha256'] = hash('sha256', (string) $validated['edit_password']);
        }

        if ($this->hasEquipeColumn()) {
            $equipeValue = trim((string) ($validated['edit_equipe'] ?? ''));
            $update['equipe'] = $equipeValue === '' ? null : $equipeValue;
        }

        if ($canChangeHierarchy) {
            $hierarchy = (string) $validated['hierarchy'];
            if ($hierarchy === 'master' && $currentRemoteUserId !== 1) {
                return back()->withErrors([
                    'hierarchy' => 'Apenas o usuario master (ID 1) pode atribuir hierarquia Master.',
                ])->withInput();
            }

            $permissions = $this->discoverPermissions();
            $matrix = $this->mergeMatrixWithPermissions($this->loadMatrix(), $permissions);
            $permissionsForHierarchy = $matrix[$hierarchy] ?? [];
            $permissionsJson = json_encode($permissionsForHierarchy, JSON_UNESCAPED_SLASHES);
            if ($permissionsJson === false) {
                return back()->withErrors([
                    'hierarchy' => 'Nao foi possivel gerar as permissoes da hierarquia.',
                ])->withInput();
            }
            $update['role'] = $hierarchy;
            $update['permissions_config_json'] = $permissionsJson;
        }

        try {
            $updatedRows = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->where('id', $userId)
                ->update($update);
        } catch (\Throwable) {
            return back()->withErrors([
                'edit_login' => 'Falha ao atualizar o usuario.',
            ])->withInput();
        }

        if ($updatedRows === 0) {
            return back()->withErrors([
                'edit_login' => 'Usuario nao encontrado para atualizacao.',
            ])->withInput();
        }

        return back()->with('status', 'users-updated');
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
            'add_equipe_mode' => ['nullable', 'string', 'in:existing,new'],
            'add_equipe_existing' => ['nullable', 'string', 'max:120'],
            'add_equipe_new' => ['nullable', 'string', 'max:120'],
        ], [
            'add_login.required' => 'Informe o login.',
            'add_password.required' => 'Informe a senha.',
            'add_password.min' => 'A senha deve ter no minimo 6 caracteres.',
            'add_password.confirmed' => 'A confirmacao da senha nao confere.',
            'add_hierarchy.required' => 'Selecione a hierarquia.',
            'add_hierarchy.in' => 'Hierarquia invalida.',
            'add_equipe_mode.in' => 'Modo de equipe invalido.',
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
        $equipeValue = null;
        if ($this->hasEquipeColumn()) {
            $equipeMode = (string) ($validated['add_equipe_mode'] ?? 'existing');
            if ($equipeMode === 'new') {
                $equipeValue = trim((string) ($validated['add_equipe_new'] ?? ''));
                if ($equipeValue === '') {
                    return back()->withErrors([
                        'add_equipe_new' => 'Informe o nome da nova equipe.',
                    ])->withInput();
                }
            } else {
                $equipeValue = trim((string) ($validated['add_equipe_existing'] ?? ''));
                if ($equipeValue === '') {
                    return back()->withErrors([
                        'add_equipe_existing' => 'Selecione uma equipe existente ou escolha criar nova.',
                    ])->withInput();
                }
            }
        }

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
                    'equipe' => $equipeValue,
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
        return $this->hasCurrentUserPermission($this->currentRemotePermissions($request), 'settings.users.store');
    }

    /**
     * @return array<string, bool>
     */
    private function currentRemotePermissions(Request $request): array
    {
        $login = Str::lower(trim((string) $request->user()?->name));
        if ($login === '') {
            return [];
        }

        try {
            $permissionsJson = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->whereRaw('LOWER(login) = ?', [$login])
                ->value('permissions_config_json');
        } catch (\Throwable) {
            return [];
        }

        $decoded = json_decode((string) $permissionsJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, bool> $permissions
     */
    private function hasCurrentUserPermission(array $permissions, string $permissionKey): bool
    {
        if (array_key_exists($permissionKey, $permissions)) {
            return (bool) $permissions[$permissionKey];
        }

        if (Str::startsWith($permissionKey, 'settings.users.')) {
            return (bool) ($permissions['settings.users'] ?? false);
        }

        if (Str::startsWith($permissionKey, 'settings.permissions.')) {
            return (bool) ($permissions['settings.permissions'] ?? false);
        }

        return false;
    }

    private function currentRemoteUserId(Request $request): ?int
    {
        $login = Str::lower(trim((string) $request->user()?->name));
        if ($login === '') {
            return null;
        }

        try {
            $remoteUserId = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->whereRaw('LOWER(login) = ?', [$login])
                ->value('id');
        } catch (\Throwable) {
            return null;
        }

        return $remoteUserId === null ? null : (int) $remoteUserId;
    }

    private function redirectBackWithDeleteErrors(Request $request, array $errors): RedirectResponse
    {
        return back()
            ->withErrors($errors)
            ->withInput($request->except('current_password'));
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
            return 'Configuracoes';
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
            'settings.users' => 'Usuarios',
            'settings.users.store' => 'Criar usuario',
            'settings.users.unlock-password' => 'Liberar senha',
            'settings.users.update-hierarchy' => 'Alterar hierarquia',
            'settings.users.update' => 'Editar usuario',
            'settings.users.delete' => 'Excluir usuario',
            'settings.permissions' => 'Permissoes',
            'settings.permissions.update' => 'Salvar alteracoes',
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
     * @param array<int, array{key: string, label: string, group: string, type: string, method: string}> $permissions
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
