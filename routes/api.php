<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TermController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// público: saúde e login
Route::get('/health', [HealthController::class, 'health']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:tcm-login');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth.api');

// termos (CRUD)
Route::middleware(['auth.api', 'throttle:tcm-terms'])->group(function () {
    Route::get('/termos', [TermController::class, 'index']);
    Route::post('/termos', [TermController::class, 'store']);
    Route::put('/termos/{id}', [TermController::class, 'update'])->whereNumber('id');
    Route::delete('/termos/{id}', [TermController::class, 'destroy'])->whereNumber('id');
});

// busca/coleta e leitura do cache
Route::middleware(['auth.api', 'throttle:tcm-read'])->group(function () {
    Route::get('/edicao', [SearchController::class, 'currentEdition']);
    Route::get('/edicoes', [SearchController::class, 'editions']);
    Route::get('/ocorrencias', [SearchController::class, 'occurrences']);
    Route::get('/resumo', [SearchController::class, 'summary']);
    Route::get('/processos', [SearchController::class, 'processes']);
});

Route::post('/buscar', [SearchController::class, 'search'])
    ->middleware(['auth.api', 'throttle:tcm-search']);

// contas do painel (exclusivo do superadmin)
Route::middleware(['auth.api', 'role'])->group(function () {
    Route::get('/usuarios', [UserController::class, 'index'])->middleware('throttle:tcm-read');
    Route::post('/usuarios', [UserController::class, 'store'])->middleware('throttle:tcm-terms');
    Route::put('/usuarios/{id}', [UserController::class, 'update'])->whereNumber('id')->middleware('throttle:tcm-terms');
    Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->whereNumber('id')->middleware('throttle:tcm-terms');
});
