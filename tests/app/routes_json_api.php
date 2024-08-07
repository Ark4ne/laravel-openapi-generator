<?php

use Illuminate\Support\Facades\Route;
use Test\app\Http\Controllers;

Route::apiResource('user', Controllers\JsonApi\UserController::class)->only(['index', 'show']);

Route::put('user/{id}', [Controllers\JsonApi\UserController::class, 'update']);
Route::put('user/{id}/basic-request', [Controllers\JsonApi\UserController::class, 'updateRequest']);
Route::put('user/{id}/no-request', [Controllers\JsonApi\UserController::class, 'updateNoRequest']);

Route::post('user', [Controllers\JsonApi\UserController::class, 'store']);
Route::post('user/basic-request', [Controllers\JsonApi\UserController::class, 'storeRequest']);
Route::post('user/no-request', [Controllers\JsonApi\UserController::class, 'storeNoRequest']);

Route::patch('user/{id}', [Controllers\JsonApi\UserController::class, 'update']);
Route::patch('user/{id}/basic-request', [Controllers\JsonApi\UserController::class, 'updateRequest']);
Route::patch('user/{id}/no-request', [Controllers\JsonApi\UserController::class, 'updateNoRequest']);

Route::apiResource('comment', Controllers\JsonApi\CommentController::class)->only(['index', 'show']);
Route::apiResource('post', Controllers\JsonApi\PostController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('user', Controllers\JsonApi\UserController::class)->only(['destroy']);
    Route::apiResource('comment', Controllers\JsonApi\CommentController::class)->only(['destroy']);
    Route::apiResource('post', Controllers\JsonApi\PostController::class)->only(['destroy']);
});
