<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('fecha')->useCurrent();
            $table->string('nombre_archivo');
            $table->string('ruta_archivo', 500);
            $table->unsignedBigInteger('tamano')->default(0);
            $table->string('hash_sha256', 64)->nullable();
            $table->unsignedTinyInteger('estado')->default(1)
                ->comment('0: eliminado, 1: activo');
            $table->timestamps();

            $table->index(['fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
