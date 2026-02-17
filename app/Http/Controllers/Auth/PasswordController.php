<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'current_password.required' => 'Informe a senha atual.',
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A nova senha deve ter no minimo 6 caracteres.',
            'password.confirmed' => 'A confirmacao da senha nao confere.',
        ]);

        $login = Str::lower(trim((string) $request->user()?->name));
        if ($login === '') {
            $exception = ValidationException::withMessages([
                'current_password' => 'Falha de autenticacao do usuario atual.',
            ]);
            $exception->errorBag = 'updatePassword';
            throw $exception;
        }

        try {
            $remoteUser = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->select(['id', 'login', 'password_sha256'])
                ->whereRaw('LOWER(login) = ?', [$login])
                ->first();
        } catch (\Throwable) {
            $exception = ValidationException::withMessages([
                'current_password' => 'Nao foi possivel validar a senha atual no servidor.',
            ]);
            $exception->errorBag = 'updatePassword';
            throw $exception;
        }

        $currentPasswordSha256 = hash('sha256', (string) $validated['current_password']);
        if (! $remoteUser || ! hash_equals((string) $remoteUser->password_sha256, $currentPasswordSha256)) {
            $exception = ValidationException::withMessages([
                'current_password' => 'A senha atual esta incorreta.',
            ]);
            $exception->errorBag = 'updatePassword';
            throw $exception;
        }

        $newPasswordSha256 = hash('sha256', (string) $validated['password']);
        $updatedRows = 0;

        try {
            $updatedRows = DB::connection('lumia_sqlsrv')
                ->table('lumia_auth_users')
                ->where('id', (int) $remoteUser->id)
                ->update([
                    'password_sha256' => $newPasswordSha256,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable) {
            try {
                $updatedRows = DB::connection('lumia_sqlsrv')
                    ->table('lumia_auth_users')
                    ->where('id', (int) $remoteUser->id)
                    ->update([
                        'password_sha256' => $newPasswordSha256,
                    ]);
            } catch (\Throwable) {
                $exception = ValidationException::withMessages([
                    'password' => 'Nao foi possivel atualizar a senha no servidor.',
                ]);
                $exception->errorBag = 'updatePassword';
                throw $exception;
            }
        }

        if ($updatedRows === 0 && ! hash_equals((string) $remoteUser->password_sha256, $newPasswordSha256)) {
            $exception = ValidationException::withMessages([
                'password' => 'A senha nao foi atualizada. Tente novamente.',
            ]);
            $exception->errorBag = 'updatePassword';
            throw $exception;
        }

        return back()->with('status', 'password-updated');
    }
}
