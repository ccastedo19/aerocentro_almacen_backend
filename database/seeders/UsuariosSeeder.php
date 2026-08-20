<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $rolAdministrador = Rol::where('nombre', 'Administrador')->firstOrFail();
        $rolEncargado = Rol::where('nombre', 'Encargado de Almacen')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'cesar.castedo1@gmail.com'],
            [
                'nombre' => 'Alejandro',
                'apellido' => 'Castedo Saucedo',
                'nombre_usuario' => 'alecastedo1',
                'password' => 'alejandro2001',
                'estado' => User::ESTADO_ACTIVO,
                'rol_id' => $rolAdministrador->id,
            ]
        );

    }
}
