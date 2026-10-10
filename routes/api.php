<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

// Every route in this file is prefixed with /api/v1 (see bootstrap/app.php).

Route::get('/health', HealthController::class)->name('api.health');

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.auth.login');

// Logged-in and still active: a user deactivated mid-session is logged out on the next request.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');

    // ->can() runs the policy before the Form Request validates, so a user without
    // permission gets 403, not validation errors. Controllers still call Gate::authorize().
    Route::controller(CustomerController::class)->prefix('customers')->name('api.customers.')->group(function () {
        Route::get('/', 'index')->name('index')->can('viewAny', Customer::class);
        Route::post('/', 'store')->name('store')->can('create', Customer::class);
        Route::get('/{customer}', 'show')->name('show')->can('view', 'customer');
        Route::put('/{customer}', 'update')->name('update')->can('update', 'customer');
        Route::patch('/{customer}/status', 'updateStatus')->name('status')->can('update', 'customer');
        Route::delete('/{customer}', 'destroy')->name('destroy')->can('delete', 'customer');
    });

    // No DELETE: a product leaves the catalogue by becoming discontinued.
    Route::controller(ProductController::class)->prefix('products')->name('api.products.')->group(function () {
        Route::get('/', 'index')->name('index')->can('viewAny', Product::class);
        Route::post('/', 'store')->name('store')->can('create', Product::class);
        Route::get('/{product}', 'show')->name('show')->can('view', 'product');
        Route::put('/{product}', 'update')->name('update')->can('update', 'product');
        Route::patch('/{product}/status', 'updateStatus')->name('status')->can('update', 'product');
    });
});
