<?php

declare(strict_types=1);

use App\Http\Controllers\AiContent\StatementController;
use Illuminate\Support\Facades\Route;

Route::post('/statements', [StatementController::class, 'store']);
Route::get('/statements/{aiGeneration}', [StatementController::class, 'show'])->name('statements.show');
