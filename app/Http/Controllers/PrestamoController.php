<?php

namespace App\Http\Controllers;

use App\Models\DetallePrestamo;
use App\Models\Herramienta;
use App\Models\HerramientaUnidad;
use App\Models\Mecanico;
use App\Models\Prestamo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrestamoController extends Controller
{
    public function punto(): JsonResponse
    {
        $mecanicos = Mecanico::query()
            ->where('estado', '<>', Mecanico::ESTADO_ELIMINADO)
            ->where(function ($consulta) {
                $consulta
                    ->where('estado', Mecanico::ESTADO_ACTIVO)
                    ->orWhereExists(function ($subconsulta) {
                        $subconsulta
                            ->selectRaw('1')
                            ->from('prestamos')
                            ->join(
                                'detalles_prestamos',
                                'detalles_prestamos.prestamo_id',
                                '=',
                                'prestamos.id',
                            )
                            ->whereColumn('prestamos.mecanico_id', 'mecanicos.id')
                            ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_EN_CURSO);
                    });
            })
            ->withCount([
                'detallesPrestamos as prestamos_activos' => fn ($consulta) => $consulta
                    ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_EN_CURSO),
            ])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'nombre',
                'apellido',
                'apodo',
                'cargo',
                'imagen',
                'color',
                'estado',
            ]);

        return response()->json([
            'mecanicos' => $mecanicos,
        ]);
    }

    public function unidadesDisponibles(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $unidades = $this->consultaUnidadesDisponibles($filtros['buscar'] ?? null)
            ->get();

        return response()->json([
            'unidades' => $unidades,
        ]);
    }

    public function enUso(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $detalles = $this->consultaDetallesEnCurso()
            ->with(['prestamo.mecanico:id,nombre,apellido,apodo,cargo,estado,imagen,color'])
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->whereHas('unidad.herramienta', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('unidad.marca', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('unidad.ubicacion', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('prestamo.mecanico', function ($relacion) use ($buscar) {
                            $relacion
                                ->where('nombre', 'like', "%{$buscar}%")
                                ->orWhere('apellido', 'like', "%{$buscar}%")
                                ->orWhere('apodo', 'like', "%{$buscar}%")
                                ->orWhere('cargo', 'like', "%{$buscar}%");
                        });
                });
            })
            ->get();

        return response()->json([
            'detalles' => $detalles,
        ]);
    }

    public function activosDeMecanico(Mecanico $mecanico): JsonResponse
    {
        $this->asegurarMecanicoVisible($mecanico);

        $detalles = $this->consultaDetallesEnCurso()
            ->whereHas('prestamo', fn ($consulta) => $consulta->where('mecanico_id', $mecanico->id))
            ->get();

        return response()->json([
            'detalles' => $detalles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'mecanico_id' => ['required', 'uuid', Rule::exists('mecanicos', 'id')],
            'unidades_ids' => ['required', 'array', 'min:1', 'max:50'],
            'unidades_ids.*' => ['required', 'uuid', 'distinct'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'fecha_limite' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $mecanico = Mecanico::query()->findOrFail($datos['mecanico_id']);
        $this->asegurarMecanicoActivo($mecanico);

        $prestamo = DB::transaction(function () use ($request, $datos, $mecanico) {
            $unidades = HerramientaUnidad::query()
                ->with('herramienta:id,nombre,estado')
                ->whereIn('id', $datos['unidades_ids'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($unidades->count() !== count($datos['unidades_ids'])) {
                throw ValidationException::withMessages([
                    'unidades_ids' => ['Una o mas unidades no existen.'],
                ]);
            }

            foreach ($datos['unidades_ids'] as $unidadId) {
                $this->asegurarUnidadPrestable($unidades->get($unidadId));
            }

            $prestamo = Prestamo::create([
                'mecanico_id' => $mecanico->id,
                'usuario_id' => $request->user()->id,
                'fecha_prestamo' => now(),
                'fecha_limite' => $datos['fecha_limite'] ?? null,
                'observaciones' => $this->textoNormalizado($datos['observaciones'] ?? null),
            ]);

            foreach ($datos['unidades_ids'] as $unidadId) {
                $unidad = $unidades->get($unidadId);

                $prestamo->detalles()->create([
                    'herramienta_unidad_id' => $unidad->id,
                    'estado' => DetallePrestamo::ESTADO_EN_CURSO,
                ]);

                $unidad->update(['estado' => HerramientaUnidad::ESTADO_PRESTADA]);
            }

            return $prestamo;
        });

        $prestamo->load([
            'mecanico:id,nombre,apellido,apodo,cargo,estado,imagen,color',
            'detalles.unidad.herramienta:id,nombre,categoria_id',
            'detalles.unidad.herramienta.categoria:id,nombre',
            'detalles.unidad.marca:id,nombre',
            'detalles.unidad.ubicacion:id,nombre',
        ]);

        return response()->json([
            'message' => 'Prestamo registrado correctamente.',
            'prestamo' => $prestamo,
        ], 201);
    }

    public function devolverDetalle(DetallePrestamo $detallePrestamo): JsonResponse
    {
        $this->devolverDetalles(collect([$detallePrestamo]));

        return response()->json([
            'message' => 'Herramienta devuelta correctamente.',
        ]);
    }

    public function devolverTodas(Mecanico $mecanico): JsonResponse
    {
        $this->asegurarMecanicoVisible($mecanico);

        $detalles = DetallePrestamo::query()
            ->where('estado', DetallePrestamo::ESTADO_EN_CURSO)
            ->whereHas('prestamo', fn ($consulta) => $consulta->where('mecanico_id', $mecanico->id))
            ->get();

        if ($detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'mecanico' => ['El mecanico no tiene herramientas prestadas.'],
            ]);
        }

        $this->devolverDetalles($detalles);

        return response()->json([
            'message' => 'Todas las herramientas fueron devueltas correctamente.',
        ]);
    }

    public function devolverAbsoluto(): JsonResponse
    {
        $detalles = DetallePrestamo::query()
            ->where('estado', DetallePrestamo::ESTADO_EN_CURSO)
            ->get();

        if ($detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'prestamo' => ['No hay herramientas prestadas para devolver.'],
            ]);
        }

        $this->devolverDetalles($detalles);

        return response()->json([
            'message' => 'Todas las herramientas del almacén fueron devueltas correctamente.',
        ]);
    }

    private function consultaUnidadesDisponibles(?string $buscar)
    {
        return HerramientaUnidad::query()
            ->with([
                'herramienta:id,nombre,categoria_id,estado',
                'herramienta.categoria:id,nombre',
                'marca:id,nombre',
                'ubicacion:id,nombre',
            ])
            ->where('herramientas_unidades.estado', HerramientaUnidad::ESTADO_DISPONIBLE)
            ->whereHas('herramienta', fn ($consulta) => $consulta
                ->where('estado', Herramienta::ESTADO_ACTIVO))
            ->when($buscar, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->whereHas('herramienta', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('marca', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('ubicacion', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"))
                        ->orWhereHas('herramienta.categoria', fn ($relacion) => $relacion
                            ->where('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->orderByDesc('herramientas_unidades.created_at');
    }

    private function consultaDetallesEnCurso()
    {
        return DetallePrestamo::query()
            ->with([
                'prestamo:id,mecanico_id,fecha_prestamo,usuario_id',
                'unidad:id,herramienta_id,marca_id,ubicacion_id,color_primario,color_secundario,tamano,estado,observaciones',
                'unidad.herramienta:id,nombre,categoria_id',
                'unidad.herramienta.categoria:id,nombre',
                'unidad.marca:id,nombre',
                'unidad.ubicacion:id,nombre',
            ])
            ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_EN_CURSO)
            ->orderByDesc('detalles_prestamos.created_at');
    }

    private function devolverDetalles($detalles): void
    {
        DB::transaction(function () use ($detalles) {
            $ids = $detalles->pluck('id')->all();

            $bloqueados = DetallePrestamo::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($ids as $id) {
                $detalle = $bloqueados->get($id);

                if (! $detalle || ! $detalle->estaEnCurso()) {
                    throw ValidationException::withMessages([
                        'detalle' => ['Una o mas unidades ya fueron devueltas.'],
                    ]);
                }

                $unidad = HerramientaUnidad::query()
                    ->whereKey($detalle->herramienta_unidad_id)
                    ->lockForUpdate()
                    ->first();

                $detalle->update([
                    'estado' => DetallePrestamo::ESTADO_DEVUELTO,
                    'fecha_devolucion' => now(),
                ]);

                if ($unidad && $unidad->estado !== HerramientaUnidad::ESTADO_ELIMINADA) {
                    $unidad->update(['estado' => HerramientaUnidad::ESTADO_DISPONIBLE]);
                }
            }
        });
    }

    private function asegurarUnidadPrestable(?HerramientaUnidad $unidad): void
    {
        if (! $unidad) {
            throw ValidationException::withMessages([
                'unidades_ids' => ['Una o mas unidades no existen.'],
            ]);
        }

        $nombre = $unidad->herramienta?->nombre ?? 'seleccionada';

        if ($unidad->estado === HerramientaUnidad::ESTADO_ELIMINADA) {
            throw ValidationException::withMessages([
                'unidades_ids' => ["La unidad de {$nombre} no esta disponible."],
            ]);
        }

        if (! $unidad->estaDisponible()) {
            throw ValidationException::withMessages([
                'unidades_ids' => ["La unidad de {$nombre} ya esta prestada."],
            ]);
        }

        if (! $unidad->herramienta || ! $unidad->herramienta->estaActiva()) {
            throw ValidationException::withMessages([
                'unidades_ids' => ["La herramienta {$nombre} no esta activa."],
            ]);
        }
    }

    private function asegurarMecanicoActivo(Mecanico $mecanico): void
    {
        if ($mecanico->estado === Mecanico::ESTADO_ELIMINADO) {
            throw ValidationException::withMessages([
                'mecanico_id' => ['El mecanico no esta disponible.'],
            ]);
        }

        if (! $mecanico->estaActivo()) {
            throw ValidationException::withMessages([
                'mecanico_id' => ['El mecanico esta fuera de servicio y no puede recibir prestamos.'],
            ]);
        }
    }

    private function asegurarMecanicoVisible(Mecanico $mecanico): void
    {
        if ($mecanico->estado === Mecanico::ESTADO_ELIMINADO) {
            throw ValidationException::withMessages([
                'mecanico' => ['El mecanico no esta disponible.'],
            ]);
        }
    }

    private function textoNormalizado(mixed $texto): ?string
    {
        if (! is_string($texto)) {
            return null;
        }

        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }
}
