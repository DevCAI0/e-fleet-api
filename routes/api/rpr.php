

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RprController;



// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/rpr', [RprController::class, 'store']);
    Route::get('/rpr/unidade/{id_unidade}', [RprController::class, 'buscarPorUnidade']);
    Route::get('/rpr', [RprController::class, 'listar']);
});
