<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Herramienta;
use App\Models\HerramientaUnidad;
use App\Models\Marca;
use App\Models\Ubicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HerramientaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                Herramienta::ESTADO_ELIMINADO,
                Herramienta::ESTADO_ACTIVO,
                Herramienta::ESTADO_INACTIVO,
            ])],
            'categoria_id' => ['nullable', 'uuid', Rule::exists('categorias', 'id')],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $herramientas = Herramienta::query()
            ->with('categoria:id,nombre')
            ->withCount([
                'unidades as unidades_total' => fn ($consulta) => $consulta
                    ->where('estado', '<>', HerramientaUnidad::ESTADO_ELIMINADA),
                'unidades as unidades_disponibles' => fn ($consulta) => $consulta
                    ->where('estado', HerramientaUnidad::ESTADO_DISPONIBLE),
                'unidades as unidades_prestadas' => fn ($consulta) => $consulta
                    ->where('estado', HerramientaUnidad::ESTADO_PRESTADA),
            ])
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where('estado', $filtros['estado']),
                fn ($consulta) => $consulta->where('estado', '<>', Herramienta::ESTADO_ELIMINADO),
            )
            ->when($filtros['categoria_id'] ?? null, function ($consulta, string $categoriaId) {
                $consulta->where('categoria_id', $categoriaId);
            })
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('descripcion', 'like', "%{$buscar}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($herramientas);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(array_merge(
            $this->reglas($request),
            $this->reglasUnidades(),
        ));
        $unidades = $datos['unidades'] ?? [];
        unset($datos['unidades']);

        $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion'] ?? null);
        $datos['estado'] = Herramienta::ESTADO_ACTIVO;
        $datos['usuario_id'] = $request->user()->id;

        $herramienta = DB::transaction(function () use ($datos, $unidades) {
            $herramienta = Herramienta::create($datos);

            foreach ($unidades as $unidad) {
                $herramienta->unidades()->create([
                    'marca_id' => $unidad['marca_id'],
                    'ubicacion_id' => $unidad['ubicacion_id'],
                    'color_primario' => $unidad['color_primario'] ?? null,
                    'color_secundario' => $unidad['color_primario']
                        ? ($unidad['color_secundario'] ?? null)
                        : null,
                    'tamano' => $this->textoNormalizado($unidad['tamano'] ?? null),
                    'fecha_calibracion' => $unidad['fecha_calibracion'] ?? null,
                    'proxima_calibracion' => $unidad['proxima_calibracion'] ?? null,
                    'observaciones' => $this->textoNormalizado($unidad['observaciones'] ?? null),
                    'estado' => HerramientaUnidad::ESTADO_DISPONIBLE,
                ]);
            }

            return $herramienta;
        });

        $herramienta->load('categoria:id,nombre')->loadCount([
            'unidades as unidades_total' => fn ($consulta) => $consulta
                ->where('estado', '<>', HerramientaUnidad::ESTADO_ELIMINADA),
            'unidades as unidades_disponibles' => fn ($consulta) => $consulta
                ->where('estado', HerramientaUnidad::ESTADO_DISPONIBLE),
            'unidades as unidades_prestadas' => fn ($consulta) => $consulta
                ->where('estado', HerramientaUnidad::ESTADO_PRESTADA),
        ]);

        return response()->json([
            'message' => 'Herramienta creada correctamente.',
            'herramienta' => $herramienta,
        ], 201);
    }

    public function show(Herramienta $herramienta): JsonResponse
    {
        $herramienta->load([
            'categoria:id,nombre',
            'usuario:id,nombre,apellido',
            'unidades' => fn ($consulta) => $consulta
                ->where('estado', '<>', HerramientaUnidad::ESTADO_ELIMINADA)
                ->with(['marca:id,nombre', 'ubicacion:id,nombre'])
                ->orderByDesc('created_at'),
        ])->loadCount([
            'unidades as unidades_total' => fn ($consulta) => $consulta
                ->where('estado', '<>', HerramientaUnidad::ESTADO_ELIMINADA),
            'unidades as unidades_disponibles' => fn ($consulta) => $consulta
                ->where('estado', HerramientaUnidad::ESTADO_DISPONIBLE),
            'unidades as unidades_prestadas' => fn ($consulta) => $consulta
                ->where('estado', HerramientaUnidad::ESTADO_PRESTADA),
        ]);

        return response()->json([
            'herramienta' => $herramienta,
        ]);
    }

    public function update(Request $request, Herramienta $herramienta): JsonResponse
    {
        $datos = $request->validate($this->reglas($request, $herramienta));

        if (array_key_exists('descripcion', $datos)) {
            $datos['descripcion'] = $this->descripcionNormalizada($datos['descripcion']);
        }

        $herramienta->update($datos);

        return response()->json([
            'message' => 'Herramienta actualizada correctamente.',
            'herramienta' => $herramienta->fresh()->load('categoria:id,nombre'),
        ]);
    }

    public function destroy(Herramienta $herramienta): JsonResponse
    {
        $this->asegurarSinUnidades($herramienta);

        $herramienta->update(['estado' => Herramienta::ESTADO_ELIMINADO]);

        return response()->json([
            'message' => 'Herramienta eliminada correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, Herramienta $herramienta): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([
                Herramienta::ESTADO_ACTIVO,
                Herramienta::ESTADO_INACTIVO,
            ])],
        ]);

        if ($datos['estado'] === Herramienta::ESTADO_INACTIVO) {
            $this->asegurarSinUnidadesPrestadas($herramienta);
        }

        if ($datos['estado'] === Herramienta::ESTADO_ACTIVO) {
            validator(
                [
                    'nombre' => $herramienta->nombre,
                    'categoria_id' => $herramienta->categoria_id,
                ],
                [
                    'nombre' => $this->reglaNombreUnico($herramienta, $herramienta->categoria_id),
                ],
            )->validate();
        }

        $herramienta->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado de la herramienta actualizado correctamente.',
            'herramienta' => $herramienta->fresh()->load('categoria:id,nombre'),
        ]);
    }

    private function reglas(Request $request, ?Herramienta $herramienta = null): array
    {
        $requerido = $herramienta ? 'sometimes' : 'required';
        $categoriaId = $request->input('categoria_id', $herramienta?->categoria_id);

        return [
            'nombre' => array_merge(
                [$requerido, 'string', 'max:150'],
                $this->reglaNombreUnico($herramienta, is_string($categoriaId) ? $categoriaId : null),
            ),
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'categoria_id' => [
                $requerido,
                'uuid',
                Rule::exists('categorias', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', Categoria::ESTADO_ACTIVO),
                ),
            ],
        ];
    }

    private function reglasUnidades(): array
    {
        return [
            'unidades' => ['nullable', 'array'],
            'unidades.*.marca_id' => [
                'required',
                'uuid',
                Rule::exists('marcas', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', Marca::ESTADO_ACTIVO),
                ),
            ],
            'unidades.*.ubicacion_id' => [
                'required',
                'uuid',
                Rule::exists('ubicaciones', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', Ubicacion::ESTADO_ACTIVO),
                ),
            ],
            'unidades.*.color_primario' => [
                'nullable',
                'string',
                Rule::in(HerramientaUnidad::COLORES),
            ],
            'unidades.*.color_secundario' => [
                'nullable',
                'string',
                Rule::in(HerramientaUnidad::COLORES),
                'different:unidades.*.color_primario',
            ],
            'unidades.*.tamano' => ['nullable', 'string', 'max:50'],
            'unidades.*.fecha_calibracion' => ['nullable', 'date'],
            'unidades.*.proxima_calibracion' => ['nullable', 'date'],
            'unidades.*.observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function reglaNombreUnico(?Herramienta $herramienta = null, ?string $categoriaId = null): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail) use ($herramienta, $categoriaId): void {
                $nombreUnico = mb_strtolower(trim((string) $value));

                if ($nombreUnico === '' || ! $categoriaId) {
                    return;
                }

                $existe = Herramienta::query()
                    ->where('nombre_unico', $nombreUnico)
                    ->where('categoria_id', $categoriaId)
                    ->when($herramienta, fn ($consulta) => $consulta->whereKeyNot($herramienta->id))
                    ->exists();

                if ($existe) {
                    $fail('Ya existe una herramienta con ese nombre en la categoria.');
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

    private function textoNormalizado(mixed $texto): ?string
    {
        if (! is_string($texto)) {
            return null;
        }

        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    private function asegurarSinUnidades(Herramienta $herramienta): void
    {
        if ($herramienta->tieneUnidadesNoEliminadas()) {
            throw ValidationException::withMessages([
                'herramienta' => ['No se puede eliminar una herramienta con unidades registradas.'],
            ]);
        }
    }

    private function asegurarSinUnidadesPrestadas(Herramienta $herramienta): void
    {
        if ($herramienta->tieneUnidadesPrestadas()) {
            throw ValidationException::withMessages([
                'herramienta' => ['No se puede desactivar una herramienta con unidades prestadas.'],
            ]);
        }
    }
}
