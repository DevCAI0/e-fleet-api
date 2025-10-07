<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UnidadeController;

Route::middleware('auth:sanctum')->prefix('unidades')->group(function () {
    Route::get('/com-rpr', [UnidadeController::class, 'veiculosComRpr']);
    Route::get('/mapa/completo', [UnidadeController::class, 'mapaCompleto']);

    Route::get('/mapa', [UnidadeController::class, 'mapa']);
    Route::get('/estatisticas', [UnidadeController::class, 'estatisticas']);
    Route::get('/{id}', [UnidadeController::class, 'show']);
    Route::get('/regiao/{regiao}', [UnidadeController::class, 'porRegiao']);
});
