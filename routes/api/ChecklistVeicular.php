<?php

use App\Http\Controllers\ChecklistVeicularController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/veiculos-manutencao', [ChecklistVeicularController::class, 'veiculosManutencao']);
    Route::get('/veiculo/{id_unidade}/status', [ChecklistVeicularController::class, 'statusVeiculo']);

    Route::get('/checklists/{id}', [ChecklistVeicularController::class, 'show']);
    Route::post('/checklists', [ChecklistVeicularController::class, 'store']);
    Route::patch('/checklists/{id}/item', [ChecklistVeicularController::class, 'atualizarItem']);
    Route::post('/checklists/{id}/upload-foto', [ChecklistVeicularController::class, 'uploadFoto']);
    Route::post('/checklists/{id}/finalizar', [ChecklistVeicularController::class, 'finalizar']);
});
