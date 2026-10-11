<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderActivityController;
use App\Http\Controllers\Api\V1\OrderAssignmentController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderNoteController;
use App\Http\Controllers\Api\V1\OrderStatusController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductStockController;
use App\Models\Customer;
use App\Models\Order;
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
    Route::get('/lookups', LookupController::class)->name('api.lookups');

    // Own notifications only; no permission needed beyond being logged in.
    Route::controller(NotificationController::class)->prefix('notifications')->name('api.notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/unread-count', 'unreadCount')->name('unread-count');
        Route::post('/read-all', 'markAllRead')->name('read-all');
        Route::post('/{id}/read', 'markRead')->name('read')->whereUuid('id');
    });

    // Permission names work as abilities through spatie's Gate integration.
    Route::controller(DashboardController::class)->prefix('dashboard')->name('api.dashboard.')->group(function () {
        Route::get('/summary', 'summary')->name('summary')->can('dashboard.view');
        Route::get('/revenue-trend', 'revenueTrend')->name('revenue-trend')->can('dashboard.view');
        Route::get('/status-distribution', 'statusDistribution')->name('status-distribution')->can('dashboard.view');
        Route::get('/my-work', 'myWork')->name('my-work')->can('orders.view_assigned');
    });

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

    Route::controller(OrderController::class)->prefix('orders')->name('api.orders.')->group(function () {
        Route::get('/', 'index')->name('index')->can('viewAny', Order::class);
        Route::post('/', 'store')->name('store')->can('create', Order::class);
        Route::get('/{order}', 'show')->name('show')->can('view', 'order');
        Route::put('/{order}', 'update')->name('update')->can('update', 'order');
        Route::delete('/{order}', 'destroy')->name('destroy')->can('delete', 'order');
    });

    // Which target status a user may choose is checked in the controller, once the input is known.
    Route::controller(OrderStatusController::class)->prefix('orders/{order}')->name('api.orders.')->group(function () {
        Route::post('/status', 'store')->name('status')->can('changeStatusOf', 'order');
        Route::get('/allowed-transitions', 'allowedTransitions')->name('allowed-transitions')->can('view', 'order');
        Route::get('/status-history', 'history')->name('status-history')->can('view', 'order');
    });
    Route::prefix('orders/{order}')->name('api.orders.')->group(function () {
        Route::post('/assign', [OrderAssignmentController::class, 'store'])->name('assign')->can('assign', 'order');
        Route::get('/notes', [OrderNoteController::class, 'index'])->name('notes.index')->can('view', 'order');
        Route::post('/notes', [OrderNoteController::class, 'store'])->name('notes.store')->can('addNote', 'order');
        Route::get('/activities', [OrderActivityController::class, 'index'])->name('activities')->can('view', 'order');
    });

    // No DELETE: a product leaves the catalogue by becoming discontinued.
    Route::controller(ProductController::class)->prefix('products')->name('api.products.')->group(function () {
        Route::get('/', 'index')->name('index')->can('viewAny', Product::class);
        Route::post('/', 'store')->name('store')->can('create', Product::class);
        Route::get('/{product}', 'show')->name('show')->can('view', 'product');
        Route::put('/{product}', 'update')->name('update')->can('update', 'product');
        Route::patch('/{product}/status', 'updateStatus')->name('status')->can('update', 'product');
    });
    Route::post('/products/{product}/stock-adjustments', [ProductStockController::class, 'store'])
        ->name('api.products.stock-adjustments')
        ->can('update', 'product');
});
