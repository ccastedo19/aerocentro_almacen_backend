<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mecanicos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('nro_licencia', 50)->nullable();
            $table->string('cargo', 100);
            $table->string('telefono', 30)->nullable();
            $table->string('imagen')->nullable();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminado, 1: activo, 2: fuera de servicio temporal');
            $table->string('nro_licencia_unico', 50)
                ->nullable()
                ->storedAs("IF(estado = 0, NULL, LOWER(TRIM(nro_licencia)))")
                ->unique();
            $table->foreignUuid('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mecanicos');
    }
};
