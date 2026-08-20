<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('usuario.activo')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::middleware('can:administrar-usuarios')->group(function () {
            Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado']);
            Route::apiResource('usuarios', UsuarioController::class);
        });
    });
});
