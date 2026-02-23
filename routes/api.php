<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Distributed Order Processing System
|--------------------------------------------------------------------------
*/

// Health Check
Route::get('/health', HealthController::class);

// Products
Route::get('/products', [ProductController::class, 'index']);

// Orders
Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show'])->where('id', '[0-9]+');
Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->where('id', '[0-9]+');
