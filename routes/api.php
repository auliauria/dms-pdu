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
    Route::get('/my-files/{folder?}', [FileController::class, 'myFiles'])->where('folder', '.*');
    Route::post('/create-folder', [FileController::class, 'createFolder']);
    Route::post('/upload-files', [FileController::class, 'store']);
});

Route::get('email/verify/{id}/{hash}', [UserController::class, 'verifyEmail'])
    ->name('verification.verify');

Route::get('email/resend', [UserController::class, 'resendVerificationEmail'])
    ->name('verification.resend');


