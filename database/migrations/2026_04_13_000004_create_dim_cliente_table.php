<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_cliente', function (Blueprint $table) {
            $table->id('cliente_id');
            $table->integer('codigo_cliente')->unique()->nullable();
            $table->string('nome', 255)->default('SEM INFORMAÇÃO');
            $table->string('email', 255)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            
            $table->index('codigo_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_cliente');
    }
};
