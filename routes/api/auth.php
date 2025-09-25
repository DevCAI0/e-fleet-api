<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

/*
|--------------------------------------------------------------------------
| Authentication API Routes
|--------------------------------------------------------------------------
*/

// Rotas públicas de autenticação
Route::prefix('auth')->group(function () {
    // Login
    Route::post('/login', [AuthController::class, 'login']);
});

// Rotas protegidas por autenticação
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Informações do usuário autenticado
    Route::get('/me', [AuthController::class, 'me']);

    // Refresh token
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Verificar se tem permissão específica
    Route::post('/check-permission', [AuthController::class, 'checkPermission']);
});
