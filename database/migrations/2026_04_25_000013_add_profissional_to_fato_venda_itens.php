<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fato_venda_itens', function (Blueprint $table) {
            $table->integer('codigo_profissional')->nullable()->after('codigo_item_catalogo');
            $table->string('profissional_nome', 120)->nullable()->after('codigo_profissional');

            $table->index('codigo_profissional');
        });
    }

    public function down(): void
    {
        Schema::table('fato_venda_itens', function (Blueprint $table) {
            $table->dropIndex(['codigo_profissional']);
            $table->dropColumn(['codigo_profissional', 'profissional_nome']);
        });
    }
};
