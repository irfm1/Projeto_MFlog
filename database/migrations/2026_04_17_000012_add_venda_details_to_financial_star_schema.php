<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fato_logs_financeiro', function (Blueprint $table) {
            $table->integer('codigo_venda')->nullable()->after('cliente_id');
            $table->index('codigo_venda');
        });

        Schema::create('fato_venda_itens', function (Blueprint $table) {
            $table->id('venda_item_id');
            $table->unsignedBigInteger('log_financeiro_id');
            $table->integer('codigo_venda')->index();
            $table->integer('codigo_item')->nullable();
            $table->integer('codigo_item_catalogo')->nullable();
            $table->string('tipo_item', 20)->default('OUTRO');
            $table->string('nome_item', 255);
            $table->decimal('quantidade', 10, 2)->default(1);
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('log_financeiro_id')
                ->references('log_financeiro_id')
                ->on('fato_logs_financeiro')
                ->onDelete('cascade');

            $table->index(['log_financeiro_id', 'tipo_item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fato_venda_itens');

        Schema::table('fato_logs_financeiro', function (Blueprint $table) {
            $table->dropIndex(['codigo_venda']);
            $table->dropColumn('codigo_venda');
        });
    }
};
