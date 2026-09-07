<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_recepcion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero_orden', 30)->unique();
            $table->string('marca', 50);
            $table->string('modelo', 100);
            $table->string('serie', 100);
            $table->string('matricula', 50)->nullable();
            $table->boolean('bimotor')->default(false);
            $table->string('motor_posicion', 20)->nullable()
                ->comment('izquierdo | derecho — solo cuando bimotor = true');
            $table->foreignUuid('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminado, 1: borrador, 2: finalizado');
            $table->foreignUuid('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_recepcion');
    }
};
