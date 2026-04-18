<?php

namespace App\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimUsuarioAlvo extends Model
{
    protected $table = 'dim_usuario_alvo';
    protected $primaryKey = 'usuario_alvo_id';

    protected $fillable = [
        'codigo_usuario',
        'nome',
    ];

    public static function buscarOuCriar(int $codigoUsuario = null, string $nome = 'SEM INFORMAÇÃO')
    {
        if ($codigoUsuario) {
            return self::firstOrCreate(
                ['codigo_usuario' => $codigoUsuario],
                ['nome' => $nome]
            );
        }
        return null;
    }
}
