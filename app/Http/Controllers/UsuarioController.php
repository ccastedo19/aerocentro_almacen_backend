<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', Rule::in([
                User::ESTADO_ELIMINADO,
                User::ESTADO_ACTIVO,
                User::ESTADO_INACTIVO,
            ])],
            'por_pagina' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $usuarios = User::query()
            ->with('rol')
            ->when(isset($filtros['estado']), function ($consulta) use ($filtros) {
                $consulta->where('estado', $filtros['estado']);
            })
            ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar) {
                $consulta->where(function ($subconsulta) use ($buscar) {
                    $subconsulta
                        ->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('apellido', 'like', "%{$buscar}%")
                        ->orWhere('nombre_usuario', 'like', "%{$buscar}%")
                        ->orWhere('email', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->paginate($filtros['por_pagina'] ?? 15);

        return response()->json($usuarios);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas($request));
        $datos['estado'] = User::ESTADO_ACTIVO;

        $usuario = User::create($datos)->load('rol');

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'usuario' => $usuario,
        ], 201);
    }

    public function show(User $usuario): JsonResponse
    {
        return response()->json([
            'usuario' => $usuario->load('rol'),
        ]);
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $datos = $request->validate($this->reglas($request, $usuario));

        if (! $request->filled('password')) {
            unset($datos['password']);
        }

        $usuario->update($datos);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'usuario' => $usuario->fresh()->load('rol'),
        ]);
    }

    public function destroy(Request $request, User $usuario): JsonResponse
    {
        if ($request->user()?->is($usuario)) {
            throw ValidationException::withMessages([
                'usuario' => ['No puedes eliminar tu propio usuario.'],
            ]);
        }

        $usuario->update(['estado' => User::ESTADO_ELIMINADO]);
        $usuario->tokens()->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    public function cambiarEstado(Request $request, User $usuario): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'integer', Rule::in([
                User::ESTADO_ACTIVO,
                User::ESTADO_INACTIVO,
            ])],
        ]);

        if ($request->user()?->is($usuario) && $datos['estado'] !== User::ESTADO_ACTIVO) {
            throw ValidationException::withMessages([
                'estado' => ['No puedes desactivar tu propio usuario.'],
            ]);
        }

        if ($datos['estado'] === User::ESTADO_ACTIVO) {
            validator([
                'nombre_usuario' => $usuario->nombre_usuario,
                'email' => $usuario->email,
            ], [
                'nombre_usuario' => [
                    Rule::unique('usuarios', 'nombre_usuario')
                        ->where(fn ($consulta) => $consulta->where('estado', '<>', User::ESTADO_ELIMINADO))
                        ->ignore($usuario->id),
                ],
                'email' => [
                    Rule::unique('usuarios', 'email')
                        ->where(fn ($consulta) => $consulta->where('estado', '<>', User::ESTADO_ELIMINADO))
                        ->ignore($usuario->id),
                ],
            ])->validate();
        }

        $usuario->update(['estado' => $datos['estado']]);

        if ($datos['estado'] === User::ESTADO_INACTIVO) {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'message' => 'Estado del usuario actualizado correctamente.',
            'usuario' => $usuario->fresh()->load('rol'),
        ]);
    }

    private function reglas(Request $request, ?User $usuario = null): array
    {
        $requerido = $usuario ? 'sometimes' : 'required';

        return [
            'nombre' => [$requerido, 'string', 'max:100'],
            'apellido' => [$requerido, 'string', 'max:100'],
            'nombre_usuario' => [
                $requerido,
                'string',
                'alpha_dash',
                'max:30',
                Rule::unique('usuarios', 'nombre_usuario')
                    ->where(fn ($consulta) => $consulta->where('estado', '<>', User::ESTADO_ELIMINADO))
                    ->ignore($usuario?->id),
            ],
            'email' => [
                $requerido,
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('usuarios', 'email')
                    ->where(fn ($consulta) => $consulta->where('estado', '<>', User::ESTADO_ELIMINADO))
                    ->ignore($usuario?->id),
            ],
            'password' => [
                $usuario ? 'sometimes' : 'required',
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'rol_id' => [
                $requerido,
                'uuid',
                Rule::exists('rol', 'id'),
            ],
        ];
    }
}
