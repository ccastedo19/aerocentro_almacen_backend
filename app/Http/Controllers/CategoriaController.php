<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                Categoria::ESTADO_ELIMINADO,
                Categoria::ESTADO_ACTIVO,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $categorias = Categoria::query()
            ->with('padre:id,nombre,parent_id')
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where('estado', $filtros['estado']),
                fn ($consulta) => $consulta->where('estado', Categoria::ESTADO_ACTIVO),
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

        return response()->json($categorias);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion'] ?? null);
        $datos['estado'] = Categoria::ESTADO_ACTIVO;

        $categoria = Categoria::create($datos)->load('padre:id,nombre,parent_id');

        return response()->json([
            'message' => 'Categoria creada correctamente.',
            'categoria' => $categoria->load('padre:id,nombre,parent_id'),
        ], 201);
    }

    public function show(Categoria $categoria): JsonResponse
    {
        return response()->json([
            'categoria' => $categoria->load('padre:id,nombre,parent_id'),
        ]);
    }

    public function update(Request $request, Categoria $categoria): JsonResponse
    {
        $datos = $request->validate($this->reglas($categoria));

        if (array_key_exists('descripcion', $datos)) {
            $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion']);
        }

        $categoria->update($datos);

        return response()->json([
            'message' => 'Categoria actualizada correctamente.',
            'categoria' => $categoria->fresh()->load('padre:id,nombre,parent_id'),
        ]);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        $this->asegurarSinHijosActivos($categoria);
        $this->asegurarSinHerramientasActivas($categoria);

        $categoria->update(['estado' => Categoria::ESTADO_ELIMINADO]);

        return response()->json([
            'message' => 'Categoria eliminada correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, Categoria $categoria): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([
                Categoria::ESTADO_ELIMINADO,
                Categoria::ESTADO_ACTIVO,
            ])],
        ]);

        if ($datos['estado'] === Categoria::ESTADO_ELIMINADO) {
            $this->asegurarSinHijosActivos($categoria);
            $this->asegurarSinHerramientasActivas($categoria);
        }

        if ($datos['estado'] === Categoria::ESTADO_ACTIVO) {
            $this->asegurarPadreActivo($categoria);
            validator(
                ['nombre' => $categoria->nombre],
                ['nombre' => $this->reglaNombreUnico($categoria)],
            )->validate();
        }

        $categoria->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado de la categoria actualizado correctamente.',
            'categoria' => $categoria->fresh()->load('padre:id,nombre,parent_id'),
        ]);
    }

    private function reglas(?Categoria $categoria = null): array
    {
        $requerido = $categoria ? 'sometimes' : 'required';

        return [
            'nombre' => array_merge(
                [$requerido, 'string', 'max:100'],
                $this->reglaNombreUnico($categoria),
            ),
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('categorias', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', Categoria::ESTADO_ACTIVO),
                ),
                function (string $attribute, mixed $value, \Closure $fail) use ($categoria): void {
                    if ($categoria && is_string($value) && $categoria->creariaCiclo($value)) {
                        $fail('La categoria padre no puede ser la misma categoria ni una descendiente.');
                    }
                },
            ],
        ];
    }

    private function reglaNombreUnico(?Categoria $categoria = null): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail) use ($categoria): void {
                $nombreUnico = mb_strtolower(trim((string) $value));

                if ($nombreUnico === '') {
                    return;
                }

                $existe = Categoria::query()
                    ->where('nombre_unico', $nombreUnico)
                    ->when($categoria, fn ($consulta) => $consulta->whereKeyNot($categoria->id))
                    ->exists();

                if ($existe) {
                    $fail('El nombre de la categoria ya esta en uso.');
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

    private function asegurarSinHerramientasActivas(Categoria $categoria): void
    {
        if ($categoria->tieneHerramientasActivas()) {
            throw ValidationException::withMessages([
                'categoria' => ['No se puede eliminar una categoria con herramientas activas.'],
            ]);
        }
    }

    private function asegurarSinHijosActivos(Categoria $categoria): void
    {
        if ($categoria->tieneHijosActivos()) {
            throw ValidationException::withMessages([
                'categoria' => ['No se puede eliminar una categoria que tiene subcategorias activas.'],
            ]);
        }
    }

    private function asegurarPadreActivo(Categoria $categoria): void
    {
        if ($categoria->parent_id && ! $categoria->padre()->where('estado', Categoria::ESTADO_ACTIVO)->exists()) {
            throw ValidationException::withMessages([
                'categoria' => ['No se puede activar una categoria cuyo padre no esta activo.'],
            ]);
        }
    }
}
