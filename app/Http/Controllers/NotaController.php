<?php

namespace App\Http\Controllers;

use App\Models\Nota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotaController extends Controller
{
    /**
     * Obtiene la nota única del sistema.
     * Si no existe ninguna, crea una por defecto.
     */
    public function show(): JsonResponse
    {
        $nota = Nota::with('usuario:id,nombre,apellido,nombre_usuario')->first();

        if (! $nota) {
            $nota = Nota::create([
                'titulo' => 'Anotaciones del Almacén',
                'contenido' => '',
            ]);
            $nota->load('usuario:id,nombre,apellido,nombre_usuario');
        }

        return response()->json([
            'nota' => $nota,
        ]);
    }

    /**
     * Actualiza la nota única del sistema.
     */
    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['nullable', 'string', 'max:255'],
            'contenido' => ['nullable', 'string'],
        ]);

        $nota = Nota::first();

        if (! $nota) {
            $nota = Nota::create([
                'titulo' => $datos['titulo'] ?? 'Anotaciones del Almacén',
                'contenido' => $datos['contenido'] ?? '',
                'usuario_id' => $request->user()?->id,
            ]);
        } else {
            $nota->update([
                'titulo' => array_key_exists('titulo', $datos) ? ($datos['titulo'] ?? 'Anotaciones del Almacén') : $nota->titulo,
                'contenido' => $datos['contenido'] ?? '',
                'usuario_id' => $request->user()?->id,
            ]);
        }

        $nota->load('usuario:id,nombre,apellido,nombre_usuario');

        return response()->json([
            'message' => 'Nota actualizada correctamente.',
            'nota' => $nota,
        ]);
    }
}
