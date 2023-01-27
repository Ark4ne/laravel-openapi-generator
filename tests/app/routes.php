<?php

use Illuminate\Support\Facades\Route;
use Test\app\Http\Controllers;

Route::apiResource('user', Controllers\UserController::class)->only(['index', 'show']);

Route::put('user/{id}', [Controllers\UserController::class, 'update']);
Route::put('user/{id}/basic-request', [Controllers\UserController::class, 'updateRequest']);
Route::put('user/{id}/no-request', [Controllers\UserController::class, 'updateNoRequest']);

Route::post('user', [Controllers\UserController::class, 'store']);
Route::post('user/basic-request', [Controllers\UserController::class, 'storeRequest']);
Route::post('user/no-request', [Controllers\UserController::class, 'storeNoRequest']);

Route::patch('user/{id}', [Controllers\UserController::class, 'update']);
Route::patch('user/{id}/basic-request', [Controllers\UserController::class, 'updateRequest']);
Route::patch('user/{id}/no-request', [Controllers\UserController::class, 'updateNoRequest']);

Route::apiResource('comment', Controllers\CommentController::class)->only(['index', 'show']);
Route::apiResource('post', Controllers\PostController::class)->only(['index', 'show']);

