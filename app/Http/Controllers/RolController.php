<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\JsonResponse;

class RolController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Rol::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json([
            'roles' => $roles,
        ]);
    }
}
