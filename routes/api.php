<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImportController;
use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public routes (rate limited)
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register')->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login')->middleware('throttle:10,1');

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        // Auth routes
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

        // Product routes
        Route::prefix('products')->name('products.')->controller(ProductController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/search', 'search')->name('search');
            Route::get('/low-stock', 'lowStock')->name('lowStock');
            // Product import endpoint (throttled to prevent abuse)
            Route::post('/import', [ProductImportController::class, 'store'])->name('import')->middleware('throttle:5,1');
            Route::get('/{product}', 'show')->name('show');
            Route::put('/{product}', 'update')->name('update');
            Route::delete('/{product}', 'destroy')->name('destroy');
        });

        // Import status route (Product imports)
        Route::get('/imports/{import}', [ProductImportController::class, 'show'])->name('imports.show');

        // Order routes (throttle state-changing actions)
        Route::prefix('orders')->name('orders.')->controller(OrderController::class)->middleware('throttle:20,1')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            // CSV import (order-import feature removed)
            Route::get('/{order}', 'show')->name('show');
            Route::post('/{order}/confirm', 'confirm')->name('confirm');
            Route::post('/{order}/ship', 'ship')->name('ship');
            Route::post('/{order}/deliver', 'deliver')->name('deliver');
            Route::post('/{order}/cancel', 'cancel')->name('cancel');
            Route::post('/{order}/invoice', 'generateInvoice')->name('invoice.generate');
        });
    });
});
