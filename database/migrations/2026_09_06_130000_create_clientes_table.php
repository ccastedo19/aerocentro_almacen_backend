<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre_completo', 200);
            $table->string('apodo', 100)->nullable();
            $table->string('nit', 50)->nullable();
            $table->string('correo', 200)->nullable();
            $table->string('celular', 30)->nullable();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminado, 1: activo');
            $table->foreignUuid('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
