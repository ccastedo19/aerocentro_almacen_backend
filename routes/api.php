<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\HerramientaCombinadaController;
use App\Http\Controllers\HerramientaController;
use App\Http\Controllers\HerramientaUnidadController;
use App\Http\Controllers\HistorialMovimientoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\MecanicoController;
use App\Http\Controllers\OrdenRecepcionController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UbicacionController;
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
        Route::get('/inicio', [InicioController::class, 'index']);

        Route::patch('/categorias/{categoria}/estado', [CategoriaController::class, 'cambiarEstado']);
        Route::apiResource('categorias', CategoriaController::class);

        Route::patch('/ubicaciones/{ubicacion}/estado', [UbicacionController::class, 'cambiarEstado']);
        Route::apiResource('ubicaciones', UbicacionController::class)
            ->parameters(['ubicaciones' => 'ubicacion']);

        Route::patch('/marcas/{marca}/estado', [MarcaController::class, 'cambiarEstado']);
        Route::apiResource('marcas', MarcaController::class);

        Route::patch('/mecanicos/{mecanico}/estado', [MecanicoController::class, 'cambiarEstado']);
        Route::post('/mecanicos/{mecanico}', [MecanicoController::class, 'update']);
        Route::apiResource('mecanicos', MecanicoController::class);

        Route::patch('/herramientas/{herramienta}/estado', [HerramientaController::class, 'cambiarEstado']);
        Route::apiResource('herramientas', HerramientaController::class);

        Route::apiResource('herramientas-unidades', HerramientaUnidadController::class)
            ->parameters(['herramientas-unidades' => 'unidad']);

        Route::patch('/herramientas-combinadas/{combinada}/estado', [HerramientaCombinadaController::class, 'cambiarEstado']);
        Route::apiResource('herramientas-combinadas', HerramientaCombinadaController::class)
            ->parameters(['herramientas-combinadas' => 'combinada']);

        Route::patch('/clientes/{cliente}/estado', [ClienteController::class, 'cambiarEstado']);
        Route::apiResource('clientes', ClienteController::class);

        Route::get('/ordenes-recepcion/{ordenRecepcion}/pdf', [OrdenRecepcionController::class, 'pdf']);
        Route::post('/ordenes-recepcion/{ordenRecepcion}/finalizar', [OrdenRecepcionController::class, 'finalizar']);
        Route::apiResource('ordenes-recepcion', OrdenRecepcionController::class)
            ->parameters(['ordenes-recepcion' => 'ordenRecepcion']);

        Route::get('/prestamos/historial', [HistorialMovimientoController::class, 'index']);
        Route::get('/prestamos/historial/general', [HistorialMovimientoController::class, 'general']);
        Route::get('/prestamos/historial/mecanicos', [HistorialMovimientoController::class, 'mecanicos']);
        Route::get('/prestamos/historial/mecanicos/{mecanico}', [HistorialMovimientoController::class, 'showMecanico']);
        Route::get('/prestamos/historial/{herramienta}', [HistorialMovimientoController::class, 'show']);

        Route::get('/prestamos/punto', [PrestamoController::class, 'punto']);
        Route::get('/prestamos/unidades-disponibles', [PrestamoController::class, 'unidadesDisponibles']);
        Route::get('/prestamos/en-uso', [PrestamoController::class, 'enUso']);
        Route::get('/prestamos/mecanicos/{mecanico}', [PrestamoController::class, 'activosDeMecanico']);
        Route::post('/prestamos', [PrestamoController::class, 'store']);
        Route::post('/prestamos/intercambiar', [PrestamoController::class, 'intercambiar']);
        Route::post('/prestamos/detalles/devolver-multiples', [PrestamoController::class, 'devolverMultiples']);
        Route::post('/prestamos/detalles/{detalle_prestamo}/devolver', [PrestamoController::class, 'devolverDetalle']);
        Route::post('/prestamos/mecanicos/{mecanico}/devolver-todas', [PrestamoController::class, 'devolverTodas']);
        Route::post('/prestamos/devolver-todas-absoluto', [PrestamoController::class, 'devolverAbsoluto']);

        Route::middleware('can:administrar-usuarios')->group(function () {
            Route::get('/roles', [RolController::class, 'index']);
            Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado']);
            Route::apiResource('usuarios', UsuarioController::class);

            Route::get('/backups', [BackupController::class, 'index']);
            Route::post('/backups', [BackupController::class, 'store']);
            Route::post('/backups/descargar', [BackupController::class, 'descargar']);
            Route::post('/backups/{backup}/restaurar', [BackupController::class, 'restaurar']);
            Route::delete('/backups/{backup}', [BackupController::class, 'destroy']);
        });
    });
});
