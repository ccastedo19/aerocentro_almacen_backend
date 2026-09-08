<?php

namespace App\Http\Controllers;

use App\Models\OrdenRecepcion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrdenRecepcionController extends Controller
{
    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar'     => ['nullable', 'string', 'max:150'],
            'tipo'       => ['nullable', 'string', Rule::in(OrdenRecepcion::TIPOS)],
            'estado'     => ['nullable', 'integer', Rule::in([
                OrdenRecepcion::ESTADO_BORRADOR,
                OrdenRecepcion::ESTADO_FINALIZADO,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $ordenes = OrdenRecepcion::query()
            ->with([
                'cliente:id,nombre_completo,nit',
                'usuario:id,nombre,apellido',
                'items',
            ])
            ->when(
                ! empty($filtros['tipo']),
                fn ($q) => $q->where('tipo', $filtros['tipo']),
            )
            ->when(
                array_key_exists('estado', $filtros),
                fn ($q) => $q->where('estado', $filtros['estado']),
                fn ($q) => $q->where('estado', '<>', OrdenRecepcion::ESTADO_ELIMINADO),
            )
            ->when($filtros['buscar'] ?? null, function ($q, string $buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('numero_orden', 'like', "%{$buscar}%")
                        ->orWhere('modelo',       'like', "%{$buscar}%")
                        ->orWhere('serie',         'like', "%{$buscar}%")
                        ->orWhere('matricula',     'like', "%{$buscar}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nombre_completo', 'like', "%{$buscar}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($ordenes);
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglasOrden());
        $items = $request->validate(['items' => ['required', 'array', 'min:1']])['items'];
        $this->validarItems($items);

        $orden = DB::transaction(function () use ($request, $datos, $items) {
            $tipo                  = $datos['tipo'] ?? OrdenRecepcion::TIPO_MOTOR;
            $datos['tipo']         = $tipo;
            $datos['numero_orden'] = $this->generarNumeroOrden($tipo);
            $datos['estado']       = OrdenRecepcion::ESTADO_BORRADOR;
            $datos['usuario_id']   = $request->user()->id;

            if (! ($datos['bimotor'] ?? false)) {
                $datos['motor_posicion'] = null;
            }

            $orden = OrdenRecepcion::create($datos);

            $this->sincronizarItems($orden, $items);

            return $orden->fresh()->load([
                'cliente:id,nombre_completo,nit,correo,celular',
                'usuario:id,nombre,apellido',
                'items',
            ]);
        });

        return response()->json([
            'message' => 'Orden de recepción creada correctamente.',
            'orden'   => $orden,
        ], 201);
    }

    // ── Ver ───────────────────────────────────────────────────────────────────

    public function show(OrdenRecepcion $ordenRecepcion): JsonResponse
    {
        return response()->json([
            'orden' => $ordenRecepcion->load([
                'cliente:id,nombre_completo,nit,correo,celular',
                'usuario:id,nombre,apellido',
                'items',
            ]),
        ]);
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function update(Request $request, OrdenRecepcion $ordenRecepcion): JsonResponse
    {
        if ($ordenRecepcion->esFinalizado()) {
            throw ValidationException::withMessages([
                'orden' => ['No se puede editar una orden finalizada.'],
            ]);
        }

        $datos = $request->validate($this->reglasOrden(update: true));

        $items = null;
        if ($request->has('items')) {
            $items = $request->validate(['items' => ['array', 'min:1']])['items'];
            $this->validarItems($items);
        }

        $orden = DB::transaction(function () use ($datos, $items, $ordenRecepcion) {
            if (isset($datos['tipo']) && $datos['tipo'] !== $ordenRecepcion->tipo) {
                $datos['numero_orden'] = $this->generarNumeroOrden($datos['tipo']);
            }

            if (! ($datos['bimotor'] ?? $ordenRecepcion->bimotor)) {
                $datos['motor_posicion'] = null;
            }

            $ordenRecepcion->update($datos);

            if ($items !== null) {
                $this->sincronizarItems($ordenRecepcion, $items);
            }

            return $ordenRecepcion->fresh()->load([
                'cliente:id,nombre_completo,nit,correo,celular',
                'usuario:id,nombre,apellido',
                'items',
            ]);
        });

        return response()->json([
            'message' => 'Orden de recepción actualizada correctamente.',
            'orden'   => $orden,
        ]);
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function destroy(OrdenRecepcion $ordenRecepcion): JsonResponse
    {
        $ordenRecepcion->update(['estado' => OrdenRecepcion::ESTADO_ELIMINADO]);

        return response()->json([
            'message' => 'Orden de recepción eliminada correctamente.',
        ]);
    }

    // ── Finalizar + PDF ───────────────────────────────────────────────────────

    public function finalizar(OrdenRecepcion $ordenRecepcion): JsonResponse
    {
        if ($ordenRecepcion->esFinalizado()) {
            throw ValidationException::withMessages([
                'orden' => ['La orden ya está finalizada.'],
            ]);
        }

        if ($ordenRecepcion->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['La orden debe tener al menos un ítem para finalizar.'],
            ]);
        }

        $ordenRecepcion->update(['estado' => OrdenRecepcion::ESTADO_FINALIZADO]);

        $ordenConDatos = $ordenRecepcion->fresh()->load([
            'cliente:id,nombre_completo,nit,correo,celular',
            'usuario:id,nombre,apellido',
            'items',
        ]);

        $pdf = Pdf::loadView('pdf.orden_recepcion', ['orden' => $ordenConDatos])
            ->setPaper('letter', 'portrait')
            ->setOption(['isPhpEnabled' => true, 'isRemoteEnabled' => true]);

        return response()->json([
            'message'  => 'Orden finalizada correctamente.',
            'orden'    => $ordenConDatos,
            'pdf_base64' => base64_encode($pdf->output()),
        ]);
    }

    // ── Generar PDF de una orden ya finalizada ────────────────────────────────

    public function pdf(OrdenRecepcion $ordenRecepcion): JsonResponse
    {
        $ordenConDatos = $ordenRecepcion->load([
            'cliente:id,nombre_completo,nit,correo,celular',
            'usuario:id,nombre,apellido',
            'items',
        ]);

        $pdf = Pdf::loadView('pdf.orden_recepcion', ['orden' => $ordenConDatos])
            ->setPaper('letter', 'portrait')
            ->setOption(['isPhpEnabled' => true, 'isRemoteEnabled' => true]);

        return response()->json([
            'pdf_base64' => base64_encode($pdf->output()),
        ]);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function reglasOrden(bool $update = false): array
    {
        $req = $update ? 'sometimes' : 'required';

        return [
            'tipo'           => ['sometimes', 'string', Rule::in(OrdenRecepcion::TIPOS)],
            'marca'          => [$req, 'string', Rule::in(OrdenRecepcion::MARCAS)],
            'modelo'         => [$req, 'string', 'max:100'],
            'serie'          => [$req, 'string', 'max:100'],
            'matricula'      => [$req, 'string', 'max:50'],
            'cliente_id'     => [$req, 'uuid', 'exists:clientes,id'],
        ];
    }

    private function validarItems(array $items): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            if (empty(trim($item['componente'] ?? ''))) {
                $errors["items.{$index}.componente"] = ["El componente es obligatorio."];
            }

            $cantidad = $item['cantidad'] ?? 1;
            if (! is_numeric($cantidad) || (int) $cantidad < 1) {
                $errors["items.{$index}.cantidad"] = ["La cantidad debe ser mayor a 0."];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function sincronizarItems(OrdenRecepcion $orden, array $items): void
    {
        $orden->items()->delete();

        $registros = array_map(
            function (array $item, int $index) use ($orden): array {
                return [
                    'id'          => (string) \Illuminate\Support\Str::uuid(),
                    'orden_id'    => $orden->id,
                    'numero_item' => $index + 1,
                    'part_number' => $this->textoNormalizado($item['part_number'] ?? null),
                    'componente'  => trim($item['componente']),
                    'cantidad'    => max(1, (int) ($item['cantidad'] ?? 1)),
                    'serie'       => $this->textoNormalizado($item['serie'] ?? null),
                    'observacion' => $this->textoNormalizado($item['observacion'] ?? null),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            },
            $items,
            array_keys($items),
        );

        $orden->items()->insert($registros);
    }

    private function generarNumeroOrden(string $tipo = OrdenRecepcion::TIPO_MOTOR): string
    {
        $prefix = match ($tipo) {
            OrdenRecepcion::TIPO_NDT => 'H12-NDT-',
            default                  => 'H12-MT-',
        };

        $numeros = OrdenRecepcion::lockForUpdate()
            ->where('tipo', $tipo)
            ->where('numero_orden', 'like', "{$prefix}%")
            ->pluck('numero_orden');

        $max = 0;
        foreach ($numeros as $num) {
            $partes = explode('-', $num);
            $val = (int) end($partes);
            if ($val > $max) {
                $max = $val;
            }
        }

        $siguiente = $max + 1;

        return sprintf('%s%02d', $prefix, $siguiente);
    }

    private function textoNormalizado(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }
}
