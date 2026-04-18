<?php

namespace App\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimProfissional extends Model
{
    protected $table = 'dim_profissional';
    protected $primaryKey = 'profissional_id';

    protected $fillable = [
        'codigo_profissional',
        'nome',
        'especialidade',
        'telefone',
        'ativo',
    ];

    public static function buscarOuCriar(int $codigoProfissional = null, string $nome = 'SEM INFORMAÇÃO')
    {
        if ($codigoProfissional) {
            return self::firstOrCreate(
                ['codigo_profissional' => $codigoProfissional],
                ['nome' => $nome]
            );
        }
        return null;
    }
}
