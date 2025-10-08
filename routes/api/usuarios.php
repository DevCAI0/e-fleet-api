<?php

use App\Http\Controllers\UsuarioLocalizacaoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    // Rotas de localização de usuários
    Route::prefix('usuarios/localizacao')->group(function () {
        Route::post('/atualizar', [UsuarioLocalizacaoController::class, 'atualizarLocalizacao']);
        Route::post('/desativar', [UsuarioLocalizacaoController::class, 'desativar']);
        Route::post('/vincular-veiculo', [UsuarioLocalizacaoController::class, 'vincularVeiculo']);
        Route::post('/desvincular-veiculo', [UsuarioLocalizacaoController::class, 'desvincularVeiculo']);
        Route::get('/meu-status', [UsuarioLocalizacaoController::class, 'meuStatus']); // NOVO
    });

    // Rota para buscar técnicos no mapa
    Route::get('/tecnicos/mapa', [UsuarioLocalizacaoController::class, 'tecnicos']);
});
