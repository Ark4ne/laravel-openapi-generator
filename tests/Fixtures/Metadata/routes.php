<?php

declare(strict_types=1);

use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\Admin\ReportController;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('api/users', [UserController::class, 'index'])->name('users.index');
Route::get('api/users/{user}', [UserController::class, 'show'])->name('users.show');
Route::post('api/users', [UserController::class, 'store'])->name('users.store');
Route::get('api/users/legacy', [UserController::class, 'legacy']);
Route::post('api/users/{user}/restore', [UserController::class, 'restore']);
Route::get('api/admin/reports', [ReportController::class, 'index'])->name('admin.reports.index');
Route::get('api/ping', /** Ping the API. */ static fn(): string => 'pong');
