<?php

namespace App\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimModulo extends Model
{
    protected $table = 'dim_modulo';
    protected $primaryKey = 'modulo_id';

    protected $fillable = [
        'nome',
        'descricao',
    ];

    public static function buscarOuCriar(string $nome)
    {
        return self::firstOrCreate(
            ['nome' => $nome],
            ['descricao' => '']
        );
    }
}
