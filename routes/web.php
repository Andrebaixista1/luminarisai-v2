<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\SettingsPermissionsController;
use App\Http\Controllers\Settings\SettingsUsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'route.permission'])->name('dashboard');

Route::middleware(['auth', 'route.permission'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/configuracoes/usuarios', [SettingsUsersController::class, 'index'])->name('settings.users');
    Route::post('/configuracoes/usuarios/adicionar', [SettingsUsersController::class, 'storeUser'])->name('settings.users.store');
    Route::post('/configuracoes/usuarios/liberar-senha', [SettingsUsersController::class, 'unlockPassword'])->name('settings.users.unlock-password');
    Route::post('/configuracoes/usuarios/atualizar-hierarquia', [SettingsUsersController::class, 'updateHierarchy'])->name('settings.users.update-hierarchy');
    Route::post('/configuracoes/usuarios/editar', [SettingsUsersController::class, 'updateUser'])->name('settings.users.update');
    Route::post('/configuracoes/usuarios/excluir', [SettingsUsersController::class, 'deleteUser'])->name('settings.users.delete');
    Route::get('/configuracoes/permissoes', [SettingsPermissionsController::class, 'index'])->name('settings.permissions');
    Route::post('/configuracoes/permissoes', [SettingsPermissionsController::class, 'update'])->name('settings.permissions.update');
});

require __DIR__.'/auth.php';
