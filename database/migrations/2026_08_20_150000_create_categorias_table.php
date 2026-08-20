<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminado, 1: activo');
            $table->string('nombre_unico', 100)
                ->nullable()
                ->storedAs("IF(estado = 0, NULL, LOWER(TRIM(nombre)))")
                ->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
