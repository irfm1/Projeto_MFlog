<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fato_logs_sistema', function (Blueprint $table) {
            $table->id('log_id');
            
            // Chaves estrangeiras para dimensões
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('profissional_id')->nullable();
            $table->unsignedBigInteger('modulo_id')->nullable();
            $table->unsignedBigInteger('tipo_operacao_id')->nullable();
            $table->integer('data_key');
            $table->time('hora_evento');
            
            // Descritores
            $table->string('descricao', 500);
            $table->integer('codigo_movimentado')->nullable();
            $table->string('nome_movimentado', 255)->nullable();
            
            // Medidas
            $table->decimal('valor_movimentado', 12, 2)->default(0);
            $table->integer('quantidade')->default(1);
            
            // Dados de auditoria
            $table->integer('codigo_log_firebird')->unique()->comment('PK do Firebird');
            $table->timestamp('data_sincronizacao')->useCurrent();
            $table->timestamps();
            
            // Índices para performance
            $table->foreign('usuario_id')->references('usuario_id')->on('dim_usuario')->onDelete('cascade');
            $table->foreign('profissional_id')->references('profissional_id')->on('dim_profissional')->onDelete('set null');
            $table->foreign('modulo_id')->references('modulo_id')->on('dim_modulo')->onDelete('set null');
            $table->foreign('tipo_operacao_id')->references('tipo_operacao_id')->on('dim_tipo_operacao')->onDelete('set null');
            $table->foreign('data_key')->references('data_key')->on('dim_data')->onDelete('cascade');
            
            $table->index('usuario_id');
            $table->index('profissional_id');
            $table->index('data_key');
            $table->index('tipo_operacao_id');
            $table->index('modulo_id');
            $table->index(['data_key', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fato_logs_sistema');
    }
};
