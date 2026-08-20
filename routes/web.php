<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminPackageController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('api.headers')->group(function () {
    Route::get('/health', [PublicController::class, 'health']);
    Route::get('/packages', [PublicController::class, 'packages']);
    Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);

    Route::get('/auth/session', [AccountController::class, 'session']);
    Route::post('/auth/register', [AccountController::class, 'register'])->middleware('trusted.browser');
    Route::post('/auth/login', [AccountController::class, 'login'])->middleware('trusted.browser');
    Route::post('/auth/logout', [AccountController::class, 'logout'])->middleware('trusted.browser');
    Route::get('/profile', [AccountController::class, 'profile']);
    Route::put('/profile', [AccountController::class, 'updateProfile'])->middleware('trusted.browser');

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('trusted.browser');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::post('/orders/{order}/seen', [OrderController::class, 'seen'])->whereNumber('order')->middleware('trusted.browser');
    Route::post('/push/customer', [PushSubscriptionController::class, 'storeCustomer'])->middleware('trusted.browser');
    Route::delete('/push/customer', [PushSubscriptionController::class, 'destroyCustomer'])->middleware('trusted.browser');
    Route::get('/orders/{order}/photos/{photo}', [OrderController::class, 'photo'])->whereNumber(['order', 'photo']);

    Route::get('/admin/auth/session', [AdminAuthController::class, 'session']);
    Route::post('/admin/auth/login', [AdminAuthController::class, 'login'])->middleware('trusted.browser');
    Route::post('/admin/auth/logout', [AdminAuthController::class, 'logout'])->middleware('trusted.browser');
    Route::post('/push/admin', [PushSubscriptionController::class, 'storeAdmin'])->middleware('trusted.browser');
    Route::delete('/push/admin', [PushSubscriptionController::class, 'destroyAdmin'])->middleware('trusted.browser');
    Route::get('/admin/packages', [AdminPackageController::class, 'index']);
    Route::post('/admin/packages', [AdminPackageController::class, 'store'])->middleware('trusted.browser');
    Route::put('/admin/packages/{package}', [AdminPackageController::class, 'update'])->whereNumber('package')->middleware('trusted.browser');
    Route::delete('/admin/packages/{package}', [AdminPackageController::class, 'destroy'])->whereNumber('package')->middleware('trusted.browser');
    Route::get('/admin/orders', [AdminOrderController::class, 'index']);
    Route::get('/admin/reports', [AdminReportController::class, 'show']);
    Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
    Route::put('/admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('order')->middleware('trusted.browser');
});

Route::view('/{path?}', 'app')
    ->where('path', '^(?!api(?:/|$)).*$')
    ->name('spa');
