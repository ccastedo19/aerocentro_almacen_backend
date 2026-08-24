<?php

namespace App\Http\Controllers;

use App\Models\Herramienta;
use App\Models\HerramientaUnidad;
use App\Models\Marca;
use App\Models\Ubicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HerramientaUnidadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                HerramientaUnidad::ESTADO_ELIMINADA,
                HerramientaUnidad::ESTADO_DISPONIBLE,
                HerramientaUnidad::ESTADO_PRESTADA,
            ])],
            'herramienta_id' => ['nullable', 'uuid', Rule::exists('herramientas', 'id')],
            'marca_id' => ['nullable', 'uuid', Rule::exists('marcas', 'id')],
            'ubicacion_id' => ['nullable', 'uuid', Rule::exists('ubicaciones', 'id')],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $unidades = HerramientaUnidad::query()
            ->with([
                'herramienta:id,nombre,categoria_id,estado',
                'herramienta.categoria:id,nombre',
                'marca:id,nombre',
                'ubicacion:id,nombre',
            ])
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where(
                    'herramientas_unidades.estado',
                    $filtros['estado'],
                ),
                fn ($consulta) => $consulta->where(
                    'herramientas_unidades.estado',
                    '<>',
                    HerramientaUnidad::ESTADO_ELIMINADA,
                ),
            )
            ->when($filtros['herramienta_id'] ?? null, function ($consulta, string $herramientaId) {
                $consulta->where('herramientas_unidades.herramienta_id', $herramientaId);
            })
            ->when($filtros['marca_id'] ?? null, function ($consulta, string $marcaId) {
                $consulta->where('herramientas_unidades.marca_id', $marcaId);
            })
            ->when($filtros['ubicacion_id'] ?? null, function ($consulta, string $ubicacionId) {
                $consulta->where('herramientas_unidades.ubicacion_id', $ubicacionId);
            })
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('herramientas_unidades.observaciones', 'like', "%{$buscar}%")
                        ->orWhere('herramientas_unidades.tamano', 'like', "%{$buscar}%")
                        ->orWhere('herramientas_unidades.color_primario', 'like', "%{$buscar}%")
                        ->orWhere('herramientas_unidades.color_secundario', 'like', "%{$buscar}%")
                        ->orWhereHas('herramienta', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('marca', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('ubicacion', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->orderByDesc('herramientas_unidades.created_at')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($unidades);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $datos['observaciones'] = $this->textoNormalizado($datos['observaciones'] ?? null);
        $datos['tamano'] = $this->textoNormalizado($datos['tamano'] ?? null);
        $datos = $this->normalizarColores($datos);
        $datos['estado'] = HerramientaUnidad::ESTADO_DISPONIBLE;

        $unidad = HerramientaUnidad::create($datos)->load([
            'herramienta:id,nombre,categoria_id,estado',
            'marca:id,nombre',
            'ubicacion:id,nombre',
        ]);

        return response()->json([
            'message' => 'Unidad de herramienta creada correctamente.',
            'unidad' => $unidad,
        ], 201);
    }

    public function show(HerramientaUnidad $unidad): JsonResponse
    {
        return response()->json([
            'unidad' => $unidad->load([
                'herramienta:id,nombre,descripcion,categoria_id,estado',
                'herramienta.categoria:id,nombre',
                'marca:id,nombre',
                'ubicacion:id,nombre',
            ]),
        ]);
    }

    public function update(Request $request, HerramientaUnidad $unidad): JsonResponse
    {
        $this->asegurarNoPrestada($unidad, 'No se puede editar una unidad prestada.');

        $datos = $request->validate($this->reglas($unidad));

        if (array_key_exists('observaciones', $datos)) {
            $datos['observaciones'] = $this->textoNormalizado($datos['observaciones']);
        }

        if (array_key_exists('tamano', $datos)) {
            $datos['tamano'] = $this->textoNormalizado($datos['tamano']);
        }

        $datos = $this->normalizarColores($datos);

        unset($datos['herramienta_id'], $datos['estado']);

        $unidad->update($datos);

        return response()->json([
            'message' => 'Unidad de herramienta actualizada correctamente.',
            'unidad' => $unidad->fresh()->load([
                'herramienta:id,nombre,categoria_id,estado',
                'marca:id,nombre',
                'ubicacion:id,nombre',
            ]),
        ]);
    }

    public function destroy(HerramientaUnidad $unidad): JsonResponse
    {
        $this->asegurarNoPrestada($unidad, 'No se puede eliminar una unidad prestada.');

        $unidad->update(['estado' => HerramientaUnidad::ESTADO_ELIMINADA]);

        return response()->json([
            'message' => 'Unidad de herramienta eliminada correctamente.',
        ]);
    }

    private function reglas(?HerramientaUnidad $unidad = null): array
    {
        $requerido = $unidad ? 'sometimes' : 'required';

        return [
            'herramienta_id' => [
                $unidad ? 'prohibited' : 'required',
                'uuid',
                Rule::exists('herramientas', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', Herramienta::ESTADO_ACTIVO),
                ),
            ],
            'marca_id' => [
                $requerido,
                'uuid',
                Rule::exists('marcas', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', Marca::ESTADO_ACTIVO),
                ),
            ],
            'ubicacion_id' => [
                $requerido,
                'uuid',
                Rule::exists('ubicaciones', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', Ubicacion::ESTADO_ACTIVO),
                ),
            ],
            'color_primario' => [
                'nullable',
                'string',
                Rule::in(HerramientaUnidad::COLORES),
            ],
            'color_secundario' => [
                'nullable',
                'string',
                Rule::in(HerramientaUnidad::COLORES),
                'different:color_primario',
            ],
            'tamano' => ['nullable', 'string', 'max:50'],
            'fecha_calibracion' => ['nullable', 'date'],
            'proxima_calibracion' => [
                'nullable',
                'date',
                'after_or_equal:fecha_calibracion',
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function normalizarColores(array $datos): array
    {
        if (array_key_exists('color_primario', $datos) && ! $datos['color_primario']) {
            $datos['color_primario'] = null;
            $datos['color_secundario'] = null;
        }

        return $datos;
    }

    private function textoNormalizado(mixed $texto): ?string
    {
        if (! is_string($texto)) {
            return null;
        }

        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    private function asegurarNoPrestada(HerramientaUnidad $unidad, string $mensaje): void
    {
        if ($unidad->estaPrestada()) {
            throw ValidationException::withMessages([
                'unidad' => [$mensaje],
            ]);
        }
    }
}
