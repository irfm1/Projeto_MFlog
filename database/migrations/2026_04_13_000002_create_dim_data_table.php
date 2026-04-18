<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_data', function (Blueprint $table) {
            $table->integer('data_key')->primary(); // YYYYMMDD format
            $table->date('data_completa')->unique();
            $table->unsignedTinyInteger('dia_mes');
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('trimestre');
            $table->string('dia_semana', 10);
            $table->string('mes_nome', 15);
            $table->boolean('eh_fim_semana');
            $table->boolean('eh_feriado')->default(false);
            
            $table->index('data_completa');
            $table->index('ano');
            $table->index('mes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_data');
    }
};
