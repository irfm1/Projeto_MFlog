<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_profissional', function (Blueprint $table) {
            $table->id('profissional_id');
            $table->integer('codigo_profissional')->unique()->nullable();
            $table->string('nome', 255)->default('SEM INFORMAÇÃO');
            $table->string('especialidade', 100)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            
            $table->index('codigo_profissional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_profissional');
    }
};
