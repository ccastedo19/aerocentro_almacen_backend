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
        Schema::create('notificacion_publica', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('titulo', 100);
            $table->string('mensaje', 500)->nullable();
            $table->string('imagen', 500)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacion_publica');
    }
};
