<?php

use App\Http\Controllers\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders');
    Route::apiResource('orders', OrderController::class)->except(['store', 'destroy']);
});
