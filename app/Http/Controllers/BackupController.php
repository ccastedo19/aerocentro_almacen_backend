<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function index(): JsonResponse
    {
        $backups = Backup::query()
            ->with('usuario:id,nombre,apellido')
            ->where('estado', Backup::ESTADO_ACTIVO)
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (Backup $backup): array => $this->conDisponibilidad($backup));

        return response()->json([
            'backups' => $backups,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'contenido' => ['required', 'string'],
        ], [
            'nombre.required' => 'Selecciona un archivo SQL.',
            'contenido.required' => 'Selecciona un archivo SQL.',
        ]);

        $sql = base64_decode($datos['contenido'], true);

        if ($sql === false) {
            throw ValidationException::withMessages([
                'contenido' => 'No se pudo leer el archivo. Vuelve a intentarlo.',
            ]);
        }

        if (strlen($sql) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'contenido' => 'El archivo no puede superar 20 MB.',
            ]);
        }

        try {
            $backup = $this->backups->guardarContenido(
                $request->user(),
                $sql,
                $datos['nombre'],
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
            'backup' => $this->conDisponibilidad($backup),
        ], 201);
    }

    public function descargar(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $backup = $this->backups->crear($request->user());
            $sql = $this->backups->contenido($backup);
        } catch (RuntimeException $excepcion) {
            return response()->json([
                'message' => $excepcion->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'No se pudo generar el backup.',
            ], 500);
        }

        return response()->streamDownload(
            function () use ($sql): void {
                echo $sql;
            },
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

    public function destroy(Backup $backup): JsonResponse
    {
        $this->backups->eliminarArchivo($backup);

        $backup->update([
            'estado' => Backup::ESTADO_ELIMINADO,
        ]);

        return response()->json([
            'message' => 'Backup eliminado correctamente.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function conDisponibilidad(Backup $backup): array
    {
        return $backup->toArray() + [
            'disponible' => $this->backups->estaDisponible($backup),
        ];
    }
}
