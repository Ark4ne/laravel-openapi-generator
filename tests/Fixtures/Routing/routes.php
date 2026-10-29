<?php

declare(strict_types=1);

/*
 * Routes of the v0.6 test application (tests/app/routes.php on branch v0.6), on the V1 fixtures.
 */

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\CommentController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\PostController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::apiResource('user', UserController::class)->only(['index', 'show']);

Route::put('user/{id}', [UserController::class, 'update']);
Route::put('user/{id}/basic-request', [UserController::class, 'updateRequest']);
Route::put('user/{id}/no-request', [UserController::class, 'updateNoRequest']);

Route::post('user', [UserController::class, 'store']);
Route::post('user/basic-request', [UserController::class, 'storeRequest']);
Route::post('user/no-request', [UserController::class, 'storeNoRequest']);

Route::patch('user/{id}', [UserController::class, 'update']);
Route::patch('user/{id}/basic-request', [UserController::class, 'updateRequest']);
Route::patch('user/{id}/no-request', [UserController::class, 'updateNoRequest']);

Route::apiResource('comment', CommentController::class)->only(['index', 'show']);
Route::apiResource('post', PostController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum'])->group(static function (): void {
    Route::apiResource('user', UserController::class)->only(['destroy']);
    Route::apiResource('comment', CommentController::class)->only(['destroy']);
    Route::apiResource('post', PostController::class)->only(['destroy']);
});
