<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('nombre_usuario', 30);
            $table->string('email');
            $table->string('password');
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminado, 1: activo, 2: inactivo');
            $table->string('nombre_usuario_unico', 30)
                ->nullable()
                ->storedAs("IF(estado = 0, NULL, LOWER(TRIM(nombre_usuario)))")
                ->unique();
            $table->string('email_unico')
                ->nullable()
                ->storedAs("IF(estado = 0, NULL, LOWER(TRIM(email)))")
                ->unique();
            $table->foreignUuid('rol_id')
                ->constrained('rol')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
