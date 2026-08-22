<?php

namespace App\Http\Controllers;

use App\Models\DetallePrestamo;
use App\Models\Herramienta;
use App\Models\HerramientaUnidad;
use App\Models\Mecanico;
use App\Models\Prestamo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InicioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'resumen' => $this->resumen(),
            'prestamos_por_dia' => $this->prestamosPorDia(),
            'no_devueltas' => $this->noDevueltas(),
            'top_herramientas' => $this->topHerramientas(),
            'top_mecanicos' => $this->topMecanicos(),
        ]);
    }

    private function resumen(): array
    {
        return [
            'herramientas' => Herramienta::query()
                ->where('estado', '<>', Herramienta::ESTADO_ELIMINADO)
                ->count(),
            'mecanicos' => Mecanico::query()
                ->where('estado', Mecanico::ESTADO_ACTIVO)
                ->count(),
            'prestadas' => HerramientaUnidad::query()
                ->where('estado', HerramientaUnidad::ESTADO_PRESTADA)
                ->count(),
            'disponibles' => HerramientaUnidad::query()
                ->where('estado', HerramientaUnidad::ESTADO_DISPONIBLE)
                ->count(),
        ];
    }

    private function prestamosPorDia(): array
    {
        $dias = [
            1 => ['dia' => 'Lun', 'cantidad' => 0],
            2 => ['dia' => 'Mar', 'cantidad' => 0],
            3 => ['dia' => 'Mié', 'cantidad' => 0],
            4 => ['dia' => 'Jue', 'cantidad' => 0],
            5 => ['dia' => 'Vie', 'cantidad' => 0],
            6 => ['dia' => 'Sáb', 'cantidad' => 0],
            7 => ['dia' => 'Dom', 'cantidad' => 0],
        ];

        $filas = DB::table('detalles_prestamos')
            ->join('prestamos', 'prestamos.id', '=', 'detalles_prestamos.prestamo_id')
            ->where('prestamos.fecha_prestamo', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(prestamos.fecha_prestamo) as fecha, COUNT(*) as cantidad')
            ->groupBy('fecha')
            ->get();

        foreach ($filas as $fila) {
            $indice = Carbon::parse($fila->fecha)->isoWeekday();
            $dias[$indice]['cantidad'] += (int) $fila->cantidad;
        }

        return array_values($dias);
    }

    private function noDevueltas(): array
    {
        $limite = now()->subDay();

        return DetallePrestamo::query()
            ->with([
                'prestamo:id,mecanico_id,fecha_prestamo',
                'prestamo.mecanico:id,nombre,apellido',
                'unidad:id,herramienta_id,marca_id,ubicacion_id',
                'unidad.herramienta:id,nombre',
                'unidad.marca:id,nombre',
                'unidad.ubicacion:id,nombre',
            ])
            ->where('detalles_prestamos.estado', DetallePrestamo::ESTADO_EN_CURSO)
            ->whereHas('prestamo', fn ($consulta) => $consulta
                ->where('fecha_prestamo', '<=', $limite))
            ->orderByDesc(
                Prestamo::query()
                    ->select('fecha_prestamo')
                    ->whereColumn('prestamos.id', 'detalles_prestamos.prestamo_id')
                    ->limit(1),
            )
            ->get()
            ->map(function (DetallePrestamo $detalle) {
                $unidad = $detalle->unidad;
                $herramienta = collect([
                    $unidad?->herramienta?->nombre,
                    $unidad?->marca?->nombre,
                    $unidad?->ubicacion?->nombre,
                ])->filter()->implode(' · ');

                $fechaPrestamo = $detalle->prestamo?->fecha_prestamo;
                $horas = $fechaPrestamo ? (int) $fechaPrestamo->diffInHours(now()) : 0;

                return [
                    'id' => $detalle->id,
                    'herramienta' => $herramienta !== '' ? $herramienta : 'Herramienta',
                    'mecanico' => $detalle->prestamo?->mecanico?->nombre ?: '—',
                    'retraso' => "{$horas} h",
                ];
            })
            ->values()
            ->all();
    }

    private function topHerramientas(): array
    {
        return DB::table('detalles_prestamos')
            ->join(
                'herramientas_unidades',
                'herramientas_unidades.id',
                '=',
                'detalles_prestamos.herramienta_unidad_id',
            )
            ->join('herramientas', 'herramientas.id', '=', 'herramientas_unidades.herramienta_id')
            ->where('herramientas.estado', '<>', Herramienta::ESTADO_ELIMINADO)
            ->select(
                'herramientas.id',
                'herramientas.nombre',
                DB::raw('COUNT(*) as prestamos'),
            )
            ->groupBy('herramientas.id', 'herramientas.nombre')
            ->orderByDesc('prestamos')
            ->orderBy('herramientas.nombre')
            ->limit(5)
            ->get()
            ->map(fn ($fila) => [
                'id' => $fila->id,
                'nombre' => $fila->nombre,
                'prestamos' => (int) $fila->prestamos,
            ])
            ->all();
    }

    private function topMecanicos(): array
    {
        return DB::table('detalles_prestamos')
            ->join('prestamos', 'prestamos.id', '=', 'detalles_prestamos.prestamo_id')
            ->join('mecanicos', 'mecanicos.id', '=', 'prestamos.mecanico_id')
            ->where('mecanicos.estado', '<>', Mecanico::ESTADO_ELIMINADO)
            ->select(
                'mecanicos.id',
                'mecanicos.nombre',
                'mecanicos.apellido',
                DB::raw('COUNT(*) as prestamos'),
            )
            ->groupBy('mecanicos.id', 'mecanicos.nombre', 'mecanicos.apellido')
            ->orderByDesc('prestamos')
            ->orderBy('mecanicos.nombre')
            ->orderBy('mecanicos.apellido')
            ->limit(5)
            ->get()
            ->map(fn ($fila) => [
                'id' => $fila->id,
                'nombre' => trim("{$fila->nombre} {$fila->apellido}"),
                'prestamos' => (int) $fila->prestamos,
            ])
            ->all();
    }
}
