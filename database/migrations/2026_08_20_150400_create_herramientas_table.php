<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('herramientas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminado, 1: activo, 2: inactivo');
            $table->string('nombre_unico', 150)
                ->nullable()
                ->storedAs("IF(estado = 0, NULL, LOWER(TRIM(nombre)))");
            $table->foreignUuid('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignUuid('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['nombre_unico', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('herramientas');
    }
};
