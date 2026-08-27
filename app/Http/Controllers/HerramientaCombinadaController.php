<?php

namespace App\Http\Controllers;

use App\Models\HerramientaCombinada;
use App\Models\HerramientaUnidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HerramientaCombinadaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                HerramientaCombinada::ESTADO_ELIMINADA,
                HerramientaCombinada::ESTADO_ACTIVA,
                HerramientaCombinada::ESTADO_INACTIVA,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $combinadas = HerramientaCombinada::query()
            ->with($this->relacionesUnidades())
            ->withCount('unidades as unidades_total')
            ->when(
                array_key_exists('estado', $filtros),
                fn ($consulta) => $consulta->where('estado', $filtros['estado']),
                fn ($consulta) => $consulta->where('estado', '<>', HerramientaCombinada::ESTADO_ELIMINADA),
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

        return response()->json($combinadas);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());

        $unidadesIds = $datos['unidades_ids'];
        unset($datos['unidades_ids']);

        $datos['descripcion'] = $this->textoNormalizado($datos['descripcion'] ?? null);
        $datos['estado'] = HerramientaCombinada::ESTADO_ACTIVA;
        $datos['usuario_id'] = $request->user()->id;

        $combinada = DB::transaction(function () use ($datos, $unidadesIds) {
            $combinada = HerramientaCombinada::create($datos);
            $combinada->unidades()->sync($unidadesIds);

            return $combinada;
        });

        return response()->json([
            'message' => 'Herramienta combinada creada correctamente.',
            'combinada' => $this->combinadaCargada($combinada),
        ], 201);
    }

    public function show(HerramientaCombinada $combinada): JsonResponse
    {
        return response()->json([
            'combinada' => $this->combinadaCargada($combinada),
        ]);
    }

    public function update(Request $request, HerramientaCombinada $combinada): JsonResponse
    {
        $datos = $request->validate($this->reglas($combinada));

        $unidadesIds = $datos['unidades_ids'] ?? null;
        unset($datos['unidades_ids']);

        if (array_key_exists('descripcion', $datos)) {
            $datos['descripcion'] = $this->textoNormalizado($datos['descripcion']);
        }

        DB::transaction(function () use ($combinada, $datos, $unidadesIds) {
            $combinada->update($datos);

            if ($unidadesIds !== null) {
                $combinada->unidades()->sync($unidadesIds);
            }
        });

        return response()->json([
            'message' => 'Herramienta combinada actualizada correctamente.',
            'combinada' => $this->combinadaCargada($combinada->fresh()),
        ]);
    }

    public function destroy(HerramientaCombinada $combinada): JsonResponse
    {
        $this->asegurarSinUnidadesPrestadas(
            $combinada,
            'No se puede eliminar una combinada con unidades prestadas.',
        );

        DB::transaction(function () use ($combinada) {
            $combinada->unidades()->detach();
            $combinada->update(['estado' => HerramientaCombinada::ESTADO_ELIMINADA]);
        });

        return response()->json([
            'message' => 'Herramienta combinada eliminada correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, HerramientaCombinada $combinada): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([
                HerramientaCombinada::ESTADO_ACTIVA,
                HerramientaCombinada::ESTADO_INACTIVA,
            ])],
        ]);

        if ($datos['estado'] === HerramientaCombinada::ESTADO_ACTIVA) {
            validator(
                ['nombre' => $combinada->nombre],
                ['nombre' => $this->reglaNombreUnico($combinada)],
            )->validate();
        }

        $combinada->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado de la herramienta combinada actualizado correctamente.',
            'combinada' => $this->combinadaCargada($combinada->fresh()),
        ]);
    }

    private function reglas(?HerramientaCombinada $combinada = null): array
    {
        $requerido = $combinada ? 'sometimes' : 'required';

        return [
            'nombre' => array_merge(
                [$requerido, 'string', 'max:150'],
                $this->reglaNombreUnico($combinada),
            ),
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidades_ids' => [$requerido, 'array', 'min:2', 'max:50'],
            'unidades_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('herramientas_unidades', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', '<>', HerramientaUnidad::ESTADO_ELIMINADA),
                ),
            ],
        ];
    }

    private function reglaNombreUnico(?HerramientaCombinada $combinada = null): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail) use ($combinada): void {
                $nombreUnico = mb_strtolower(trim((string) $value));

                if ($nombreUnico === '') {
                    return;
                }

                $existe = HerramientaCombinada::query()
                    ->where('nombre_unico', $nombreUnico)
                    ->when($combinada, fn ($consulta) => $consulta->whereKeyNot($combinada->id))
                    ->exists();

                if ($existe) {
                    $fail('Ya existe una herramienta combinada con ese nombre.');
                }
            },
        ];
    }

    private function relacionesUnidades(): array
    {
        return [
            'unidades' => fn ($consulta) => $consulta->with([
                'herramienta:id,nombre,estado',
                'marca:id,nombre',
                'ubicacion:id,nombre',
            ]),
        ];
    }

    private function combinadaCargada(HerramientaCombinada $combinada): HerramientaCombinada
    {
        return $combinada
            ->load($this->relacionesUnidades())
            ->loadCount('unidades as unidades_total');
    }

    private function textoNormalizado(mixed $texto): ?string
    {
        if (! is_string($texto)) {
            return null;
        }

        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    private function asegurarSinUnidadesPrestadas(HerramientaCombinada $combinada, string $mensaje): void
    {
        if ($combinada->tieneUnidadesPrestadas()) {
            throw ValidationException::withMessages([
                'combinada' => [$mensaje],
            ]);
        }
    }
}
