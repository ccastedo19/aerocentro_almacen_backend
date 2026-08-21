<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MarcaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                Marca::ESTADO_ELIMINADO,
                Marca::ESTADO_ACTIVO,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $marcas = Marca::query()
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where('estado', $filtros['estado']),
                fn ($consulta) => $consulta->where('estado', Marca::ESTADO_ACTIVO),
            )
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('descripcion', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('nombre')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($marcas);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion'] ?? null);
        $datos['estado'] = Marca::ESTADO_ACTIVO;

        $marca = Marca::create($datos);

        return response()->json([
            'message' => 'Marca creada correctamente.',
            'marca' => $marca,
        ], 201);
    }

    public function show(Marca $marca): JsonResponse
    {
        return response()->json([
            'marca' => $marca,
        ]);
    }

    public function update(Request $request, Marca $marca): JsonResponse
    {
        $datos = $request->validate($this->reglas($marca));

        if (array_key_exists('descripcion', $datos)) {
            $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion']);
        }

        $marca->update($datos);

        return response()->json([
            'message' => 'Marca actualizada correctamente.',
            'marca' => $marca->fresh(),
        ]);
    }

    public function destroy(Marca $marca): JsonResponse
    {
        $this->asegurarSinUnidadesActivas($marca);

        $marca->update(['estado' => Marca::ESTADO_ELIMINADO]);

        return response()->json([
            'message' => 'Marca eliminada correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, Marca $marca): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([
                Marca::ESTADO_ELIMINADO,
                Marca::ESTADO_ACTIVO,
            ])],
        ]);

        if ($datos['estado'] === Marca::ESTADO_ELIMINADO) {
            $this->asegurarSinUnidadesActivas($marca);
        }

        if ($datos['estado'] === Marca::ESTADO_ACTIVO) {
            validator(
                ['nombre' => $marca->nombre],
                ['nombre' => $this->reglaNombreUnico($marca)],
            )->validate();
        }

        $marca->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado de la marca actualizado correctamente.',
            'marca' => $marca->fresh(),
        ]);
    }

    private function reglas(?Marca $marca = null): array
    {
        $requerido = $marca ? 'sometimes' : 'required';

        return [
            'nombre' => array_merge(
                [$requerido, 'string', 'max:100'],
                $this->reglaNombreUnico($marca),
            ),
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function reglaNombreUnico(?Marca $marca = null): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail) use ($marca): void {
                $nombreUnico = mb_strtolower(trim((string) $value));

                if ($nombreUnico === '') {
                    return;
                }

                $existe = Marca::query()
                    ->where('nombre_unico', $nombreUnico)
                    ->when($marca, fn ($consulta) => $consulta->whereKeyNot($marca->id))
                    ->exists();

                if ($existe) {
                    $fail('El nombre de la marca ya esta en uso.');
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

    private function asegurarSinUnidadesActivas(Marca $marca): void
    {
        if ($marca->tieneUnidadesActivas()) {
            throw ValidationException::withMessages([
                'marca' => ['No se puede eliminar una marca con herramientas activas.'],
            ]);
        }
    }
}
