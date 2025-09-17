<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register-user', [UserController::class, 'register']);
Route::post('/login-user', [UserController::class, 'login']);
Route::post('/logout-user', [UserController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-files/{folder?}', [FileController::class, 'myFiles']);
});
