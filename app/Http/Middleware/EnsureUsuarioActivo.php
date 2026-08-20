<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof User || ! $usuario->estaActivo()) {
            return new JsonResponse([
                'message' => 'El usuario no se encuentra activo.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
