<?php

use App\Http\Controllers\RprCicloController;
use App\Http\Controllers\ChecklistVeicularController;
use Illuminate\Support\Facades\Route;

// routes/api.php

Route::middleware(['auth:sanctum'])->group(function () {

    // Rotas de RPR/Ciclo
    Route::prefix('rpr')->group(function () {
        // Ciclo ativo
        Route::get('/ciclo-ativo', [RprCicloController::class, 'cicloAtivo']);
        Route::get('/ciclo-ativo/veiculos', [RprCicloController::class, 'veiculosCicloAtivo']);

        // Realizar inspeção
        Route::post('/veiculo-ciclo/{id}/inspecionar', [RprCicloController::class, 'realizarInspecao']);

        // Histórico
        Route::get('/ciclos/historico', [RprCicloController::class, 'historico']);
    });

    // Rotas de Veículos em Manutenção (Ocorrências)
    Route::get('/veiculos-manutencao', [ChecklistVeicularController::class, 'veiculosManutencao']);
    Route::get('/veiculo/{id_unidade}/status', [ChecklistVeicularController::class, 'statusVeiculo']);

    // Rotas de Checklist
    Route::prefix('checklists')->group(function () {
        Route::get('/', [ChecklistVeicularController::class, 'index']);
        Route::post('/', [ChecklistVeicularController::class, 'store']);
        Route::get('/{id}', [ChecklistVeicularController::class, 'show']);
        Route::put('/{id}', [ChecklistVeicularController::class, 'update']);
        Route::patch('/{id}/item', [ChecklistVeicularController::class, 'updateItem']);
        Route::post('/{id}/finalizar', [ChecklistVeicularController::class, 'finalizar']);
    });
});
