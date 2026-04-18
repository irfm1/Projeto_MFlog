<?php

namespace App\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimUsuario extends Model
{
    protected $table = 'dim_usuario';
    protected $primaryKey = 'usuario_id';
    
    protected $fillable = [
        'codigo_usuario',
        'nome',
        'ativo',
        'data_criacao',
        'data_atualizacao',
    ];

    public $timestamps = true;

    // Relationships
    public function logsDoUsuario()
    {
        return $this->hasMany('App\Models\Facts\FatoLogsSistema', 'usuario_id', 'usuario_id');
    }

    public function logsFinanceiros()
    {
        return $this->hasMany('App\Models\Facts\FatoLogsFinanceiro', 'usuario_id', 'usuario_id');
    }

    /**
     * Busca ou cria usuário por código
     */
    public static function buscarOuCriar(int $codigoUsuario, string $nome = 'SEM INFORMAÇÃO')
    {
        $usuario = self::firstOrCreate(
            ['codigo_usuario' => $codigoUsuario],
            ['nome' => $nome]
        );

        $nomeAtual = (string) $usuario->nome;
        $nomeNovo = trim($nome);

        $isPlaceholder = $nomeAtual === ''
            || $nomeAtual === 'SEM INFORMAÇÃO'
            || preg_match('/^USER_\d+$/', $nomeAtual) === 1;

        if ($nomeNovo !== '' && $nomeNovo !== 'SEM INFORMAÇÃO' && $isPlaceholder && $nomeAtual !== $nomeNovo) {
            $usuario->nome = $nomeNovo;
            $usuario->save();
        }

        return $usuario;
    }
}
