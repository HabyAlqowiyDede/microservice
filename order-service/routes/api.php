<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => response()->json([
    'status' => 'ok',
    'service' => 'order-service',
]));

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/user/{userId}', [OrderController::class, 'byUser'])->whereNumber('userId');
Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
Route::patch('/orders/{id}/pay', [OrderController::class, 'pay'])->whereNumber('id');
