<?php

namespace App\Models\Facts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FatoLogsFinanceiro extends Model
{
    protected $table = 'fato_logs_financeiro';
    protected $primaryKey = 'log_financeiro_id';

    protected $fillable = [
        'usuario_id',
        'cliente_id',
        'codigo_venda',
        'tipo_operacao_id',
        'modulo_id',
        'data_key',
        'hora_evento',
        'tipo_movimento',
        'valor_movimento',
        'valor_cancelamento',
        'descricao',
        'justificativa',
        'codigo_log_firebird',
        'data_sincronizacao',
    ];

    protected $casts = [
        'hora_evento' => 'time',
        'valor_movimento' => 'decimal:2',
        'valor_cancelamento' => 'decimal:2',
        'data_sincronizacao' => 'datetime',
    ];

    // Relationships
    public function usuario(): BelongsTo
    {
        return $this->belongsTo('App\Models\Dimensions\DimUsuario', 'usuario_id', 'usuario_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo('App\Models\Dimensions\DimCliente', 'cliente_id', 'cliente_id');
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
