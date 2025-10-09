<?php

use App\Http\Controllers\RprController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    // ========================================
    // ROTAS DE RPR (TODAS EM UM CONTROLLER)
    // ========================================
    Route::prefix('rpr')->group(function () {

        // Tipos e Correções
        Route::get('/tipos-correcoes', [RprController::class, 'tiposCorrecoes']);

        // Salvar RPR (Formulário Manual)
        Route::post('/', [RprController::class, 'store']);

        // Ciclo Ativo
        Route::get('/ciclo-ativo', [RprController::class, 'cicloAtivo']);
        Route::get('/ciclo-ativo/veiculos', [RprController::class, 'veiculosCicloAtivo']);

        // Inspeção de Veículo no Ciclo
        Route::post('/veiculo-ciclo/{id}/inspecionar', [RprController::class, 'realizarInspecao']);

        // Listagem e Consultas
        Route::get('/', [RprController::class, 'listar']);
        Route::get('/unidade/{id_unidade}', [RprController::class, 'buscarPorUnidade']);

        // Histórico de Ciclos
        Route::get('/ciclos/historico', [RprController::class, 'historicoCiclos']);
    });
});
