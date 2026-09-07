<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar'    => ['nullable', 'string', 'max:150'],
            'estado'    => ['nullable', 'integer', Rule::in([
                Cliente::ESTADO_ELIMINADO,
                Cliente::ESTADO_ACTIVO,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $clientes = Cliente::query()
            ->with('usuario:id,nombre,apellido')
            ->when(
                array_key_exists('estado', $filtros),
                fn ($q) => $q->where('estado', $filtros['estado']),
                fn ($q) => $q->where('estado', '<>', Cliente::ESTADO_ELIMINADO),
            )
            ->when($filtros['buscar'] ?? null, function ($q, string $buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre_completo', 'like', "%{$buscar}%")
                        ->orWhere('apodo',          'like', "%{$buscar}%")
                        ->orWhere('nit',            'like', "%{$buscar}%")
                        ->orWhere('correo',         'like', "%{$buscar}%")
                        ->orWhere('celular',        'like', "%{$buscar}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($clientes);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());

        $datos['estado']     = Cliente::ESTADO_ACTIVO;
        $datos['usuario_id'] = $request->user()->id;

        $datos = $this->normalizarCampos($datos);

        $cliente = Cliente::create($datos)->load('usuario:id,nombre,apellido');

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'cliente' => $cliente,
        ], 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'cliente' => $cliente->load('usuario:id,nombre,apellido'),
        ]);
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $datos = $request->validate($this->reglas($cliente));

        $datos = $this->normalizarCampos($datos);

        $cliente->update($datos);

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'cliente' => $cliente->fresh()->load('usuario:id,nombre,apellido'),
        ]);
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        $cliente->update(['estado' => Cliente::ESTADO_ELIMINADO]);

        return response()->json([
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, Cliente $cliente): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([Cliente::ESTADO_ACTIVO])],
        ]);

        $cliente->update(['estado' => $datos['estado']]);

        return response()->json([
            'message' => 'Estado del cliente actualizado correctamente.',
            'cliente' => $cliente->fresh()->load('usuario:id,nombre,apellido'),
        ]);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function reglas(?Cliente $cliente = null): array
    {
        $requerido = $cliente ? 'sometimes' : 'required';

        return [
            'nombre_completo' => [$requerido, 'string', 'max:200'],
            'apodo'           => ['nullable', 'string', 'max:100'],
            'nit'             => ['nullable', 'string', 'max:50'],
            'correo'          => ['nullable', 'email', 'max:200'],
            'celular'         => ['nullable', 'string', 'max:30'],
        ];
    }

    private function normalizarCampos(array $datos): array
    {
        foreach (['nombre_completo', 'apodo', 'nit', 'correo', 'celular'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $datos[$campo] = $this->textoNormalizado($datos[$campo]);
            }
        }

        return $datos;
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
