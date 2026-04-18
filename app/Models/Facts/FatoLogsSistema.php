<?php

namespace App\Models\Facts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FatoLogsSistema extends Model
{
    protected $table = 'fato_logs_sistema';
    protected $primaryKey = 'log_id';

    protected $fillable = [
        'usuario_id',
        'profissional_id',
        'modulo_id',
        'tipo_operacao_id',
        'data_key',
        'hora_evento',
        'descricao',
        'codigo_movimentado',
        'nome_movimentado',
        'valor_movimentado',
        'quantidade',
        'codigo_log_firebird',
        'data_sincronizacao',
    ];

    protected $casts = [
        'hora_evento' => 'time',
        'valor_movimentado' => 'decimal:2',
        'data_sincronizacao' => 'datetime',
    ];

    // Relationships
    public function usuario(): BelongsTo
    {
        return $this->belongsTo('App\Models\Dimensions\DimUsuario', 'usuario_id', 'usuario_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo('App\Models\Dimensions\DimModulo', 'modulo_id', 'modulo_id');
    }

    public function tipoOperacao(): BelongsTo
    {
        return $this->belongsTo('App\Models\Dimensions\DimTipoOperacao', 'tipo_operacao_id', 'tipo_operacao_id');
    }

    public function data(): BelongsTo
    {
        return $this->belongsTo('App\Models\Dimensions\DimData', 'data_key', 'data_key');
    }
}
