
<?php

use App\Http\Controllers\RprCicloController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RprController;


// routes/api.php

Route::middleware(['auth:sanctum'])->prefix('rpr')->group(function () {
    // Ciclo ativo
    Route::get('/ciclo-ativo', [RprCicloController::class, 'cicloAtivo']);
    Route::get('/ciclo-ativo/veiculos', [RprCicloController::class, 'veiculosCicloAtivo']);

    // Realizar inspeção
    Route::post('/veiculo-ciclo/{id}/inspecionar', [RprCicloController::class, 'realizarInspecao']);

    // Histórico
    Route::get('/ciclos/historico', [RprCicloController::class, 'historico']);

    // RPRs antigos (compatibilidade)
    Route::get('/unidade/{id_unidade}', [RprController::class, 'buscarPorUnidade']);
});

