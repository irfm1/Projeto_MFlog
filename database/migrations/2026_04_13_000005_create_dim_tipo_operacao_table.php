<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_tipo_operacao', function (Blueprint $table) {
            $table->id('tipo_operacao_id');
            $table->string('tipo_descricao', 100)->unique();
            $table->string('categoria', 50)->default('OUTRA');
            $table->text('descricao_completa')->nullable();
            $table->timestamps();
            
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_tipo_operacao');
    }
};
