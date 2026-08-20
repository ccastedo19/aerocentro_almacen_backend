<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'credencial' => ['required', 'string'],
            'password' => ['required', 'string'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ]);

        $credencial = trim($datos['credencial']);
        $campo = filter_var($credencial, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'nombre_usuario';

        $usuario = User::query()
            ->with('rol')
            ->where($campo, $credencial)
            ->where('estado', User::ESTADO_ACTIVO)
            ->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'credencial' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $usuario
            ->createToken($datos['dispositivo'] ?? 'panel-web')
            ->plainTextToken;

        return response()->json([
            'message' => 'Sesion iniciada correctamente.',
            'token' => $token,
            'token_type' => 'Bearer',
            'usuario' => $usuario,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'usuario' => $request->user()?->load('rol'),
        ]);
    }
}
