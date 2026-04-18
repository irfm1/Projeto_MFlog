<?php

use App\Http\Controllers\LogsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'logs.index')->name('home');

// Debug route (remove after testing)
Route::get('/debug', fn() => 'System OK - PHP/Laravel responsive');
Route::view('/test-livewire', 'test-livewire')->name('test.livewire');
Route::view('/test-no-js', 'test-no-js')->name('test.no-js');

/**
 * Dashboard de Logs - Livewire Interface (SIMPLIFIED)
 */
Route::view('/logs', 'logs.index')->name('logs.dashboard');

/**
 * API de Logs - Broadcasting & Real-time Updates
 */
Route::prefix('api/logs')->group(function () {
    Route::get('/', [LogsController::class, 'index'])->name('logs.index');
    Route::get('/system', [LogsController::class, 'system'])->name('logs.system');
    Route::get('/caixa', [LogsController::class, 'caixa'])->name('logs.caixa');
    Route::get('/cancelamento', [LogsController::class, 'cancelamento'])->name('logs.cancelamento');
    //listar usuarios simples sem controller
    Route::get('/users', function () {
        return \Illuminate\Support\Facades\DB::table('dim_usuario')->select('usuario_id', 'nome')->get();
    })->name('logs.users');
    Route::get('/{id}', [LogsController::class, 'show'])->name('logs.show');
    Route::get('/export/csv', [LogsController::class, 'exportCsv'])->name('logs.export-csv');
    Route::get('/stats/overview', [LogsController::class, 'stats'])->name('logs.stats');
});
