<?php

namespace App\Http\Controllers;

use App\Models\Mecanico;
use App\Services\MecanicoImagenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MecanicoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                Mecanico::ESTADO_ELIMINADO,
                Mecanico::ESTADO_ACTIVO,
                Mecanico::ESTADO_FUERA_DE_SERVICIO,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $mecanicos = Mecanico::query()
            ->with('usuario:id,nombre,apellido')
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where('estado', $filtros['estado']),
                fn ($consulta) => $consulta->where('estado', '<>', Mecanico::ESTADO_ELIMINADO),
            )
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('apellido', 'like', "%{$buscar}%")
                        ->orWhere('apodo', 'like', "%{$buscar}%")
                        ->orWhere('nro_licencia', 'like', "%{$buscar}%")
                        ->orWhere('cargo', 'like', "%{$buscar}%")
                        ->orWhere('telefono', 'like', "%{$buscar}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($mecanicos);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->datosValidados($request);
        $datos['estado'] = Mecanico::ESTADO_ACTIVO;
        $datos['usuario_id'] = $request->user()->id;
        $datos['telefono'] = $this->telefonoNormalizado($datos['telefono'] ?? null);
        $datos['apodo'] = $this->textoNormalizado($datos['apodo'] ?? null);

        try {
            $mecanico = DB::transaction(function () use ($request, $datos) {
                $mecanico = Mecanico::create($datos);

                if ($request->hasFile('imagen')) {
                    $mecanico->update([
                        'imagen' => $this->imagenes()->subir(
                            $request->file('imagen'),
                            $mecanico->id,
                        ),
                    ]);
                }

                return $mecanico->fresh()->load('usuario:id,nombre,apellido');
            });
        } catch (Throwable $excepcion) {
            $this->lanzarErrorDeImagen($excepcion);
        }

        return response()->json([
            'message' => 'Mecanico creado correctamente.',
            'mecanico' => $mecanico,
        ], 201);
    }

    public function show(Mecanico $mecanico): JsonResponse
    {
        return response()->json([
            'mecanico' => $mecanico->load('usuario:id,nombre,apellido'),
        ]);
    }

    public function update(Request $request, Mecanico $mecanico): JsonResponse
    {
        $datos = $this->datosValidados($request, $mecanico);

        if (array_key_exists('telefono', $datos)) {
            $datos['telefono'] = $this->telefonoNormalizado($datos['telefono']);
        }

        if (array_key_exists('apodo', $datos)) {
            $datos['apodo'] = $this->textoNormalizado($datos['apodo']);
        }

        try {
            $mecanico = DB::transaction(function () use ($request, $mecanico, $datos) {
                if ($request->hasFile('imagen')) {
                    $datos['imagen'] = $this->imagenes()->subir(
                        $request->file('imagen'),
                        $mecanico->id,
                    );
                } elseif ($request->boolean('eliminar_imagen') && $mecanico->imagen) {
                    $this->imagenes()->eliminar($mecanico->id);
                    $datos['imagen'] = null;
                }

                $mecanico->update($datos);

                return $mecanico->fresh()->load('usuario:id,nombre,apellido');
            });
        } catch (Throwable $excepcion) {
            $this->lanzarErrorDeImagen($excepcion);
        }

        return response()->json([
            'message' => 'Mecanico actualizado correctamente.',
            'mecanico' => $mecanico,
        ]);
    }

    public function destroy(Mecanico $mecanico): JsonResponse
    {
        $this->asegurarSinPrestamosEnCurso($mecanico);

        $mecanico->update(['estado' => Mecanico::ESTADO_ELIMINADO]);

        if ($mecanico->imagen) {
            try {
                $this->imagenes()->eliminar($mecanico->id);
                $mecanico->update(['imagen' => null]);
            } catch (Throwable) {
                // El mecanico ya quedo eliminado; no bloqueamos la operacion por Cloudinary.
            }
        }

        return response()->json([
            'message' => 'Mecanico eliminado correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, Mecanico $mecanico): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([
                Mecanico::ESTADO_ACTIVO,
                Mecanico::ESTADO_FUERA_DE_SERVICIO,
            ])],
        ]);

        if ($datos['estado'] === Mecanico::ESTADO_FUERA_DE_SERVICIO) {
            $this->asegurarSinPrestamosEnCurso($mecanico);
        }

        if ($datos['estado'] === Mecanico::ESTADO_ACTIVO) {
            validator(
                ['nro_licencia' => $mecanico->nro_licencia],
                ['nro_licencia' => $this->reglaLicenciaUnica($mecanico)],
            )->validate();
        }

        $mecanico->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado del mecanico actualizado correctamente.',
            'mecanico' => $mecanico->fresh()->load('usuario:id,nombre,apellido'),
        ]);
    }

    private function imagenes(): MecanicoImagenService
    {
        return app(MecanicoImagenService::class);
    }

    private function datosValidados(Request $request, ?Mecanico $mecanico = null): array
    {
        $datos = $request->validate($this->reglas($mecanico));

        unset($datos['imagen'], $datos['eliminar_imagen']);

        return $datos;
    }

    private function reglas(?Mecanico $mecanico = null): array
    {
        $requerido = $mecanico ? 'sometimes' : 'required';

        return [
            'nombre' => [$requerido, 'string', 'max:100'],
            'apellido' => [$requerido, 'string', 'max:100'],
            'apodo' => ['nullable', 'string', 'max:100'],
            'nro_licencia' => array_merge(
                ['nullable', 'string', 'max:50'],
                $this->reglaLicenciaUnica($mecanico),
            ),
            'cargo' => [$requerido, 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'color' => [$requerido, 'string', Rule::in(Mecanico::COLORES)],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'eliminar_imagen' => ['sometimes', 'boolean'],
        ];
    }

    private function reglaLicenciaUnica(?Mecanico $mecanico = null): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail) use ($mecanico): void {
                $licenciaUnica = mb_strtolower(trim((string) $value));

                if ($licenciaUnica === '') {
                    return;
                }

                $existe = Mecanico::query()
                    ->where('nro_licencia_unico', $licenciaUnica)
                    ->when($mecanico, fn ($consulta) => $consulta->whereKeyNot($mecanico->id))
                    ->exists();

                if ($existe) {
                    $fail('El numero de licencia ya esta en uso.');
                }
            },
        ];
    }

    private function telefonoNormalizado(mixed $telefono): ?string
    {
        return $this->textoNormalizado($telefono);
    }

    private function textoNormalizado(mixed $texto): ?string
    {
        if (! is_string($texto)) {
            return null;
        }

        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    private function asegurarSinPrestamosEnCurso(Mecanico $mecanico): void
    {
        if ($mecanico->tienePrestamosEnCurso()) {
            throw ValidationException::withMessages([
                'mecanico' => ['No se puede completar la operacion: el mecanico tiene prestamos en curso.'],
            ]);
        }
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
