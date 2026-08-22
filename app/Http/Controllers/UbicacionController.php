<?php

namespace App\Http\Controllers;

use App\Models\Ubicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UbicacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                Ubicacion::ESTADO_ELIMINADO,
                Ubicacion::ESTADO_ACTIVO,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $ubicaciones = Ubicacion::query()
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where('estado', $filtros['estado']),
                fn ($consulta) => $consulta->where('estado', Ubicacion::ESTADO_ACTIVO),
            )
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('descripcion', 'like', "%{$buscar}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($ubicaciones);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion'] ?? null);
        $datos['estado'] = Ubicacion::ESTADO_ACTIVO;

        $ubicacion = Ubicacion::create($datos);

        return response()->json([
            'message' => 'Ubicacion creada correctamente.',
            'ubicacion' => $ubicacion,
        ], 201);
    }

    public function show(Ubicacion $ubicacion): JsonResponse
    {
        return response()->json([
            'ubicacion' => $ubicacion,
        ]);
    }

    public function update(Request $request, Ubicacion $ubicacion): JsonResponse
    {
        $datos = $request->validate($this->reglas($ubicacion));

        if (array_key_exists('descripcion', $datos)) {
            $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion']);
        }

        $ubicacion->update($datos);

        return response()->json([
            'message' => 'Ubicacion actualizada correctamente.',
            'ubicacion' => $ubicacion->fresh(),
        ]);
    }

    public function destroy(Ubicacion $ubicacion): JsonResponse
    {
        $this->asegurarSinUnidadesActivas($ubicacion);

        $ubicacion->update(['estado' => Ubicacion::ESTADO_ELIMINADO]);

        return response()->json([
            'message' => 'Ubicacion eliminada correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, Ubicacion $ubicacion): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([
                Ubicacion::ESTADO_ELIMINADO,
                Ubicacion::ESTADO_ACTIVO,
            ])],
        ]);

        if ($datos['estado'] === Ubicacion::ESTADO_ELIMINADO) {
            $this->asegurarSinUnidadesActivas($ubicacion);
        }

        if ($datos['estado'] === Ubicacion::ESTADO_ACTIVO) {
            validator(
                ['nombre' => $ubicacion->nombre],
                ['nombre' => $this->reglaNombreUnico($ubicacion)],
            )->validate();
        }

        $ubicacion->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado de la ubicacion actualizado correctamente.',
            'ubicacion' => $ubicacion->fresh(),
        ]);
    }

    private function reglas(?Ubicacion $ubicacion = null): array
    {
        $requerido = $ubicacion ? 'sometimes' : 'required';

        return [
            'nombre' => array_merge(
                [$requerido, 'string', 'max:100'],
                $this->reglaNombreUnico($ubicacion),
            ),
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function reglaNombreUnico(?Ubicacion $ubicacion = null): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail) use ($ubicacion): void {
                $nombreUnico = mb_strtolower(trim((string) $value));

                if ($nombreUnico === '') {
                    return;
                }

                $existe = Ubicacion::query()
                    ->where('nombre_unico', $nombreUnico)
                    ->when($ubicacion, fn ($consulta) => $consulta->whereKeyNot($ubicacion->id))
                    ->exists();

                if ($existe) {
                    $fail('El nombre de la ubicacion ya esta en uso.');
                }
            },
        ];
    }

    private function descripcionNormalizada(mixed $descripcion): ?string
    {
        if (! is_string($descripcion)) {
            return null;
        }

        $descripcion = trim($descripcion);

        return $descripcion === '' ? null : $descripcion;
    }

    private function asegurarSinUnidadesActivas(Ubicacion $ubicacion): void
    {
        if ($ubicacion->tieneUnidadesActivas()) {
            throw ValidationException::withMessages([
                'ubicacion' => ['No se puede eliminar una ubicacion con herramientas activas.'],
            ]);
        }
    }
}
