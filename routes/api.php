<?php

use App\Http\Controllers\Api\AssuntoController;
use App\Http\Controllers\Api\AtendimentoController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Authentication is intentionally omitted in this iteration.
    // Apply auth:sanctum (or equivalent) to finalizar, transferir and dashboard
    // before exposing these routes to the public internet.
    Route::post('/atendimentos', [AtendimentoController::class, 'store']);
    Route::get('/atendimentos/{atendimento}', [AtendimentoController::class, 'show']);
    Route::post('/atendimentos/{atendimento}/finalizar', [AtendimentoController::class, 'finalizar']);
    Route::post('/atendimentos/{atendimento}/transferir', [AtendimentoController::class, 'transferir']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/assuntos', [AssuntoController::class, 'index']);
    Route::post('/assuntos', [AssuntoController::class, 'store']);
    Route::get('/assuntos/{assunto}', [AssuntoController::class, 'show']);
    Route::put('/assuntos/{assunto}', [AssuntoController::class, 'update']);
    Route::delete('/assuntos/{assunto}', [AssuntoController::class, 'destroy']);
});
