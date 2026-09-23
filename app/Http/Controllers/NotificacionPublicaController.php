<?php

namespace App\Http\Controllers;

use App\Models\Notificacion_publica;
use App\Services\NotificacionPublicaImagenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class NotificacionPublicaController extends Controller
{
    /**
     * Endpoint público para la pantalla de mecánicos.
     * Retorna la única notificación si su estado es 1 (mostrar), o null si es 0 (no mostrar).
     */
    public function publicIndex(): JsonResponse
    {
        $notificacion = Notificacion_publica::query()->first();

        if (! $notificacion || ! $notificacion->estaVisible()) {
            return response()->json([
                'notificacion' => null,
            ]);
        }

        return response()->json([
            'notificacion' => $notificacion,
        ]);
    }

    /**
     * Obtiene la única notificación pública registrada (para el panel administrativo).
     */
    public function index(): JsonResponse
    {
        $notificacion = Notificacion_publica::query()->first();

        return response()->json([
            'notificacion' => $notificacion,
        ]);
    }

    /**
     * Guarda o actualiza la única notificación pública del sistema (Singleton).
     */
    public function guardar(Request $request): JsonResponse
    {
        $notificacion = Notificacion_publica::query()->first();

        $datos = $request->validate([
            'titulo' => [$notificacion ? 'sometimes' : 'required', 'string', 'max:100'],
            'mensaje' => ['nullable', 'string', 'max:500'],
            'estado' => ['sometimes', 'integer', Rule::in([Notificacion_publica::ESTADO_OCULTO, Notificacion_publica::ESTADO_MOSTRAR])],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'eliminar_imagen' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('mensaje', $datos)) {
            $datos['mensaje'] = $this->textoNormalizado($datos['mensaje']);
        }

        $eliminarImagenSolicitada = (bool) $request->boolean('eliminar_imagen');
        $tieneNuevaImagen = $request->hasFile('imagen');
        $imagenAnterior = $notificacion?->imagen;

        $tieneMensaje = filled(trim((string) ($datos['mensaje'] ?? ($notificacion?->mensaje ?? ''))));
        $tieneImagen = $tieneNuevaImagen || (! $eliminarImagenSolicitada && (bool) $imagenAnterior);

        if (! $tieneMensaje && ! $tieneImagen) {
            throw ValidationException::withMessages([
                'mensaje' => ['Debes proporcionar al menos un mensaje o una imagen para la notificacion.'],
            ]);
        }

        unset($datos['imagen'], $datos['eliminar_imagen']);

        try {
            $notificacionGuardada = DB::transaction(function () use ($request, $notificacion, $datos, $tieneNuevaImagen, $eliminarImagenSolicitada, $imagenAnterior) {
                if ($notificacion) {
                    $notificacion->update($datos);
                } else {
                    $datos['estado'] = $datos['estado'] ?? Notificacion_publica::ESTADO_MOSTRAR;
                    $notificacion = Notificacion_publica::create($datos);
                }

                if ($tieneNuevaImagen) {
                    $notificacion->update([
                        'imagen' => $this->imagenes()->subir(
                            $request->file('imagen'),
                            $notificacion->id,
                        ),
                    ]);
                } elseif ($eliminarImagenSolicitada && $imagenAnterior) {
                    $notificacion->update(['imagen' => null]);
                    $this->imagenes()->eliminar($notificacion->id);
                }

                return $notificacion->fresh();
            });
        } catch (Throwable $excepcion) {
            $this->lanzarErrorDeImagen($excepcion);
        }

        return response()->json([
            'message' => 'Notificacion publica guardada correctamente.',
            'notificacion' => $notificacionGuardada,
        ]);
    }

    /**
     * Cambia el estado de la notificación (0: No mostrar, 1: Mostrar).
     */
    public function cambiarEstado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([Notificacion_publica::ESTADO_OCULTO, Notificacion_publica::ESTADO_MOSTRAR])],
        ]);

        $notificacion = Notificacion_publica::query()->firstOrFail();
        $notificacion->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado de la notificacion actualizado correctamente.',
            'notificacion' => $notificacion->fresh(),
        ]);
    }

    /**
     * Elimina el registro único de notificación pública (si existe).
     */
    public function destroy(): JsonResponse
    {
        $notificacion = Notificacion_publica::query()->first();

        if ($notificacion) {
            $imagenAnterior = $notificacion->imagen;

            DB::transaction(function () use ($notificacion, $imagenAnterior) {
                $notificacion->delete();

                if ($imagenAnterior) {
                    $this->imagenes()->eliminar($notificacion->id);
                }
            });
        }

        return response()->json([
            'message' => 'Notificacion publica eliminada correctamente.',
        ]);
    }

    private function imagenes(): NotificacionPublicaImagenService
    {
        return app(NotificacionPublicaImagenService::class);
    }

    private function textoNormalizado(mixed $texto): ?string
    {
        if (! is_string($texto)) {
            return null;
        }

        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    private function lanzarErrorDeImagen(Throwable $excepcion): never
    {
        if ($excepcion instanceof ValidationException) {
            throw $excepcion;
        }

        report($excepcion);

        throw ValidationException::withMessages([
            'imagen' => [
                $excepcion instanceof RuntimeException
                    ? $excepcion->getMessage()
                    : 'No se pudo procesar la imagen en Cloudinary.',
            ],
        ]);
    }
}
