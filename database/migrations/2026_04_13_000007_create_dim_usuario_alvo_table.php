<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_usuario_alvo', function (Blueprint $table) {
            $table->id('usuario_alvo_id');
            $table->integer('codigo_usuario')->unique()->nullable();
            $table->string('nome', 255)->default('SEM INFORMAÇÃO');
            $table->timestamps();
            
            $table->index('codigo_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_usuario_alvo');
    }
};
