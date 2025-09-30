<?php

use App\Http\Controllers\ChecklistVeicularController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Checklist Veicular API Routes
|--------------------------------------------------------------------------
*/

// Rotas públicas (com autenticação via token/sanctum)
Route::middleware(['auth:sanctum'])->group(function () {

    // Listar veículos em manutenção
    Route::get('/veiculos-manutencao', [ChecklistVeicularController::class, 'veiculosManutencao']);

    // Status de um veículo específico
    Route::get('/veiculo/{id_unidade}/status', [ChecklistVeicularController::class, 'statusVeiculo']);

    // Listar checklists
    Route::get('/checklists', [ChecklistVeicularController::class, 'index']);

    // Obter checklist específico
    Route::get('/checklists/{id}', [ChecklistVeicularController::class, 'show']);

    // Criar novo checklist
    Route::post('/checklists', [ChecklistVeicularController::class, 'store']);

    // Atualizar checklist
    Route::put('/checklists/{id}', [ChecklistVeicularController::class, 'update']);

    // Atualizar item específico do checklist
    Route::patch('/checklists/{id}/item', [ChecklistVeicularController::class, 'updateItem']);

    // Finalizar checklist
    Route::post('/checklists/{id}/finalizar', [ChecklistVeicularController::class, 'finalizar']);

    // Reabrir checklist finalizado (apenas admin)
    Route::post('/checklists/{id}/reabrir', [ChecklistVeicularController::class, 'reabrir'])
        ->middleware('can:admin');

    // Dashboard/Estatísticas
    Route::get('/dashboard', [ChecklistVeicularController::class, 'dashboard']);

    // Relatórios
    Route::get('/relatorios/problemas-comuns', [ChecklistVeicularController::class, 'problemasComuns']);
    Route::get('/relatorios/tempo-medio', [ChecklistVeicularController::class, 'tempoMedio']);
    Route::get('/relatorios/por-periodo', [ChecklistVeicularController::class, 'relatorioPorPeriodo']);

    // Histórico de um veículo
    Route::get('/veiculo/{id_unidade}/historico', [ChecklistVeicularController::class, 'historicoVeiculo']);

    // Opções de correção
    Route::get('/opcoes-correcao', [ChecklistVeicularController::class, 'opcoesCorrecao']);

    // Tipos de status disponíveis
    Route::get('/tipos-status', [ChecklistVeicularController::class, 'tiposStatus']);
});
