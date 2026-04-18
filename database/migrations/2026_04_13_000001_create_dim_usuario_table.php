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
        Schema::create('dim_usuario', function (Blueprint $table) {
            $table->id('usuario_id');
            $table->integer('codigo_usuario')->unique()->comment('FK para USUARIOS_LOG.CODIGO_USUARIO');
            $table->string('nome', 255)->default('SEM INFORMAÇÃO');
            $table->boolean('ativo')->default(true);
            $table->timestamp('data_criacao')->nullable();
            $table->timestamp('data_atualizacao')->nullable();
            $table->timestamps();
            
            $table->index('codigo_usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dim_usuario');
    }
};
