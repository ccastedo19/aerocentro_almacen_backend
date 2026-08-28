<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function index(): JsonResponse
    {
        $backups = Backup::query()
            ->with('usuario:id,nombre,apellido')
            ->where('estado', Backup::ESTADO_ACTIVO)
            ->orderByDesc('fecha')
            ->get();

        return response()->json([
            'backups' => $backups,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:51200'],
        ], [
            'archivo.required' => 'Selecciona un archivo SQL.',
            'archivo.file' => 'El archivo no es valido.',
            'archivo.max' => 'El archivo no puede superar 50 MB.',
        ]);

        try {
            $backup = $this->backups->guardarArchivo(
                $request->user(),
                $request->file('archivo'),
            )->load('usuario:id,nombre,apellido');
        } catch (RuntimeException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'No se pudo cargar el backup.',
            ], 500);
        }

        return response()->json([
            'message' => 'Backup cargado correctamente.',
            'backup' => $backup,
        ], 201);
    }

    public function descargar(Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            $backup = $this->backups->crear($request->user());
        } catch (RuntimeException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'No se pudo generar el backup.',
            ], 500);
        }

        $absoluta = $this->backups->rutaAbsoluta($backup->ruta_archivo);

        return response()->download(
            $absoluta,
            $backup->nombre_archivo,
            ['Content-Type' => 'application/sql'],
        );
    }

    public function restaurar(Backup $backup): JsonResponse
    {
        try {
            $this->backups->restaurar($backup);
        } catch (RuntimeException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'No se pudo restaurar el backup.',
            ], 500);
        }

        return response()->json([
            'message' => 'Backup restaurado correctamente.',
        ]);
    }
}
