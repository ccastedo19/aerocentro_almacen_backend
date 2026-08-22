<?php

namespace App\Http\Controllers;

use App\Models\DetallePrestamo;
use App\Models\Herramienta;
use App\Models\Mecanico;
use App\Models\Prestamo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HistorialMovimientoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'uuid', Rule::exists('categorias', 'id')],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $herramientas = Herramienta::query()
            ->with('categoria:id,nombre')
            ->whereHas('detallesPrestamos')
            ->withCount([
                'detallesPrestamos as movimientos_total',
                'detallesPrestamos as movimientos_en_curso' => fn ($consulta) => $consulta
                    ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_EN_CURSO),
                'detallesPrestamos as movimientos_devueltos' => fn ($consulta) => $consulta
                    ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_DEVUELTO),
            ])
            ->addSelect([
                'ultimo_movimiento' => $this->subconsultaUltimoMovimientoHerramienta(),
            ])
            ->when($filtros['categoria_id'] ?? null, function ($consulta, string $categoriaId) {
                $consulta->where('categoria_id', $categoriaId);
            })
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('descripcion', 'like', "%{$buscar}%")
                        ->orWhereHas('categoria', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->orderByDesc('ultimo_movimiento')
            ->orderBy('nombre')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($herramientas);
    }

    public function general(Request $request): JsonResponse
    {
        $filtros = $request->validate($this->reglasFiltrosMovimiento());

        $movimientos = $this->consultaMovimientos($filtros)
            ->paginate($filtros['por_pagina'] ?? 15)
            ->through(fn (DetallePrestamo $detalle) => $this->movimiento($detalle));

        return response()->json($movimientos);
    }

    public function mecanicos(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $mecanicos = Mecanico::query()
            ->where('estado', '<>', Mecanico::ESTADO_ELIMINADO)
            ->whereHas('detallesPrestamos')
            ->withCount([
                'detallesPrestamos as movimientos_total',
                'detallesPrestamos as movimientos_en_curso' => fn ($consulta) => $consulta
                    ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_EN_CURSO),
                'detallesPrestamos as movimientos_devueltos' => fn ($consulta) => $consulta
                    ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_DEVUELTO),
            ])
            ->addSelect([
                'ultimo_movimiento' => DetallePrestamo::query()
                    ->selectRaw('MAX(COALESCE(detalles_prestamos.fecha_devolucion, prestamos.fecha_prestamo))')
                    ->join('prestamos', 'prestamos.id', '=', 'detalles_prestamos.prestamo_id')
                    ->whereColumn('prestamos.mecanico_id', 'mecanicos.id'),
            ])
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('apellido', 'like', "%{$buscar}%")
                        ->orWhere('apodo', 'like', "%{$buscar}%")
                        ->orWhere('cargo', 'like', "%{$buscar}%");
                });
            })
            ->orderByDesc('ultimo_movimiento')
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($mecanicos);
    }

    public function showMecanico(Request $request, Mecanico $mecanico): JsonResponse
    {
        if ($mecanico->estado === Mecanico::ESTADO_ELIMINADO) {
            abort(404);
        }

        $filtros = $request->validate($this->reglasFiltrosMovimiento());
        $filtros['mecanico_id'] = $mecanico->id;

        $movimientos = $this->consultaMovimientos($filtros)
            ->paginate($filtros['por_pagina'] ?? 15)
            ->through(fn (DetallePrestamo $detalle) => $this->movimiento($detalle));

        return response()->json([
            'mecanico' => [
                'id' => $mecanico->id,
                'nombre' => $mecanico->nombre,
                'apellido' => $mecanico->apellido,
                'nombre_completo' => $mecanico->nombre_completo,
                'apodo' => $mecanico->apodo,
                'cargo' => $mecanico->cargo,
                'estado' => $mecanico->estado,
            ],
            'movimientos' => $movimientos,
        ]);
    }

    public function show(Request $request, Herramienta $herramienta): JsonResponse
    {
        $filtros = $request->validate($this->reglasFiltrosMovimiento());
        $filtros['herramienta_id'] = $herramienta->id;

        $movimientos = $this->consultaMovimientos($filtros)
            ->paginate($filtros['por_pagina'] ?? 15)
            ->through(fn (DetallePrestamo $detalle) => $this->movimiento($detalle));

        $herramienta->load('categoria:id,nombre');

        return response()->json([
            'herramienta' => [
                'id' => $herramienta->id,
                'nombre' => $herramienta->nombre,
                'descripcion' => $herramienta->descripcion,
                'estado' => $herramienta->estado,
                'categoria' => $herramienta->categoria,
            ],
            'movimientos' => $movimientos,
        ]);
    }

    private function reglasFiltrosMovimiento(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                DetallePrestamo::ESTADO_DEVUELTO,
                DetallePrestamo::ESTADO_EN_CURSO,
            ])],
            'mecanico_id' => ['nullable', 'uuid', Rule::exists('mecanicos', 'id')],
            'herramienta_id' => ['nullable', 'uuid', Rule::exists('herramientas', 'id')],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    private function consultaMovimientos(array $filtros): Builder
    {
        return DetallePrestamo::query()
            ->with([
                'prestamo:id,mecanico_id,usuario_id,fecha_prestamo',
                'prestamo.mecanico:id,nombre,apellido,apodo,cargo,estado',
                'unidad:id,herramienta_id,marca_id,ubicacion_id',
                'unidad.herramienta:id,nombre,categoria_id',
                'unidad.herramienta.categoria:id,nombre',
                'unidad.marca:id,nombre',
                'unidad.ubicacion:id,nombre',
            ])
            ->when($filtros['herramienta_id'] ?? null, function ($consulta, string $herramientaId) {
                $consulta->whereHas('unidad', fn ($relacion) => $relacion
                    ->where('herramienta_id', $herramientaId));
            })
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where('detalles_prestamos.estado', $filtros['estado']),
            )
            ->when($filtros['mecanico_id'] ?? null, function ($consulta, string $mecanicoId) {
                $consulta->whereHas('prestamo', fn ($relacion) => $relacion
                    ->where('mecanico_id', $mecanicoId));
            })
            ->when($filtros['desde'] ?? null, function ($consulta, string $desde) {
                $consulta->whereHas('prestamo', fn ($relacion) => $relacion
                    ->whereDate('fecha_prestamo', '>=', $desde));
            })
            ->when($filtros['hasta'] ?? null, function ($consulta, string $hasta) {
                $consulta->whereHas('prestamo', fn ($relacion) => $relacion
                    ->whereDate('fecha_prestamo', '<=', $hasta));
            })
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->whereHas('prestamo.mecanico', function ($relacion) use ($buscar) {
                            $relacion
                                ->where('nombre', 'like', "%{$buscar}%")
                                ->orWhere('apellido', 'like', "%{$buscar}%")
                                ->orWhere('apodo', 'like', "%{$buscar}%");
                        })
                        ->orWhereHas('unidad.herramienta', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('unidad.marca', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('unidad.ubicacion', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->orderByDesc(
                Prestamo::query()
                    ->select('fecha_prestamo')
                    ->whereColumn('prestamos.id', 'detalles_prestamos.prestamo_id')
                    ->limit(1),
            )
            ->orderByDesc('detalles_prestamos.created_at');
    }

    private function subconsultaUltimoMovimientoHerramienta()
    {
        return DetallePrestamo::query()
            ->selectRaw('MAX(COALESCE(detalles_prestamos.fecha_devolucion, prestamos.fecha_prestamo))')
            ->join(
                'herramientas_unidades',
                'herramientas_unidades.id',
                '=',
                'detalles_prestamos.herramienta_unidad_id',
            )
            ->join('prestamos', 'prestamos.id', '=', 'detalles_prestamos.prestamo_id')
            ->whereColumn('herramientas_unidades.herramienta_id', 'herramientas.id');
    }

    private function movimiento(DetallePrestamo $detalle): array
    {
        $mecanico = $detalle->prestamo?->mecanico;
        $unidad = $detalle->unidad;
        $herramienta = $unidad?->herramienta;

        return [
            'id' => $detalle->id,
            'estado' => $detalle->estado,
            'estado_etiqueta' => $detalle->estaDevuelto() ? 'Devuelto' : 'En prestamo',
            'fecha_prestamo' => $detalle->prestamo?->fecha_prestamo?->toIso8601String(),
            'fecha_devolucion' => $detalle->fecha_devolucion?->toIso8601String(),
            'mecanico' => $mecanico ? [
                'id' => $mecanico->id,
                'nombre_completo' => $mecanico->nombre_completo,
                'apodo' => $mecanico->apodo,
                'cargo' => $mecanico->cargo,
            ] : null,
            'herramienta' => $herramienta ? [
                'id' => $herramienta->id,
                'nombre' => $herramienta->nombre,
                'categoria' => $herramienta->categoria,
            ] : null,
            'unidad' => $unidad ? [
                'id' => $unidad->id,
                'marca' => $unidad->marca?->only(['id', 'nombre']),
                'ubicacion' => $unidad->ubicacion?->only(['id', 'nombre']),
            ] : null,
        ];
    }
}
