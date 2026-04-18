<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fato_logs_financeiro', function (Blueprint $table) {
            $table->id('log_financeiro_id');
            
            // Chaves estrangeiras
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('tipo_operacao_id')->nullable();
            $table->unsignedBigInteger('modulo_id')->nullable();
            $table->integer('data_key');
            $table->time('hora_evento');
            
            // Tipo de movimento (CAIXA ou CANCELAMENTO)
            $table->enum('tipo_movimento', ['CAIXA', 'CANCELAMENTO'])->default('CAIXA');
            
            // Dados financeiros
            $table->decimal('valor_movimento', 12, 2)->default(0);
            $table->decimal('valor_cancelamento', 12, 2)->default(0);
            $table->string('descricao', 500)->nullable();
            $table->text('justificativa')->nullable();
            
            // Auditoria
            $table->integer('codigo_log_firebird')->unique();
            $table->timestamp('data_sincronizacao')->useCurrent();
            $table->timestamps();
            
            // Índices
            $table->foreign('usuario_id')->references('usuario_id')->on('dim_usuario')->onDelete('cascade');
            $table->foreign('cliente_id')->references('cliente_id')->on('dim_cliente')->onDelete('set null');
            $table->foreign('tipo_operacao_id')->references('tipo_operacao_id')->on('dim_tipo_operacao')->onDelete('set null');
            $table->foreign('modulo_id')->references('modulo_id')->on('dim_modulo')->onDelete('set null');
            $table->foreign('data_key')->references('data_key')->on('dim_data')->onDelete('cascade');
            
            $table->index('usuario_id');
            $table->index('cliente_id');
            $table->index('data_key');
            $table->index('tipo_movimento');
            $table->index(['data_key', 'usuario_id', 'tipo_movimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fato_logs_financeiro');
    }
};
