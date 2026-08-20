<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Rol::firstOrCreate(['nombre' => 'Administrador']);
        Rol::firstOrCreate(['nombre' => 'Encargado de Almacen']);
    }
}
