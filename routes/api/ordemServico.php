<?php

use App\Http\Controllers\OrdemServicoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('ordem-servico')->group(function () {
    // Listar OSs
    Route::get('/', [OrdemServicoController::class, 'index']);

    // Criar nova OS
    Route::post('/', [OrdemServicoController::class, 'store']);

    // Detalhes da OS
    Route::get('/{id}', [OrdemServicoController::class, 'show']);

    // Atualizar status da OS
    Route::patch('/{id}/status', [OrdemServicoController::class, 'atualizarStatus']);

    // Adicionar veículo à OS
    Route::post('/{id}/veiculos', [OrdemServicoController::class, 'adicionarVeiculo']);

    // Remover veículo da OS
    Route::delete('/{id}/veiculos/{id_veiculo}', [OrdemServicoController::class, 'removerVeiculo']);

    // Atualizar status de veículo específico
    Route::patch('/{id}/veiculos/{id_veiculo}/status', [OrdemServicoController::class, 'atualizarStatusVeiculo']);

    // Listar veículos com problemas (para adicionar em OS)
    Route::get('/veiculos/com-problemas', [OrdemServicoController::class, 'veiculosComProblemas']);
});
