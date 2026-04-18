<?php

namespace App\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimCliente extends Model
{
    protected $table = 'dim_cliente';
    protected $primaryKey = 'cliente_id';

    protected $fillable = [
        'codigo_cliente',
        'nome',
        'email',
        'telefone',
        'cidade',
        'ativo',
    ];

    public static function buscarOuCriar(int $codigoCliente, string $nome = 'SEM INFORMAÇÃO')
    {
        $cliente = self::firstOrCreate(
            ['codigo_cliente' => $codigoCliente],
            ['nome' => $nome]
        );

        $nomeAtual = (string) $cliente->nome;
        $nomeNovo = trim($nome);

        $isPlaceholder = $nomeAtual === '' || $nomeAtual === 'SEM INFORMAÇÃO';

        if ($nomeNovo !== '' && $nomeNovo !== 'SEM INFORMAÇÃO' && $isPlaceholder && $nomeAtual !== $nomeNovo) {
            $cliente->nome = $nomeNovo;
            $cliente->save();
        }

        return $cliente;
    }
}
