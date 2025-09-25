<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComandoController;

/*
|--------------------------------------------------------------------------
| Comandos API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('comandos')->group(function () {

    // Unidades disponíveis
    Route::get('/unidades', [ComandoController::class, 'unidades']);

    // Criar comandos
    Route::post('/hodometro', [ComandoController::class, 'hodometro']);
    Route::post('/reboot', [ComandoController::class, 'reboot']);
    Route::post('/velocidade-maxima', [ComandoController::class, 'velocidadeMaxima']);
    Route::post('/configurar-rede', [ComandoController::class, 'configurarRede']);

    // Listar comandos
    Route::get('/pendentes', [ComandoController::class, 'comandosPendentes']);
    Route::get('/enviados', [ComandoController::class, 'comandosEnviados']);
    Route::get('/historico', [ComandoController::class, 'historico']);
    Route::get('/logs', [ComandoController::class, 'logs']);
});
