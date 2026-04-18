<?php

namespace App\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimTipoOperacao extends Model
{
    protected $table = 'dim_tipo_operacao';
    protected $primaryKey = 'tipo_operacao_id';

    protected $fillable = [
        'tipo_descricao',
        'categoria',
        'descricao_completa',
    ];

    public static function buscarOuCriar(string $descricao, string $categoria = 'OUTRA')
    {
        return self::firstOrCreate(
            ['tipo_descricao' => $descricao],
            ['categoria' => $categoria]
        );
    }
}
