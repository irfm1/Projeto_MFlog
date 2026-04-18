<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * CancelamentoLog - Cancelamentos (Crítico)
 * 
 * Representa registros de LOG_CANCELAMENTO do Firebird
 * Total: 3,666 registros
 * 
 * Trilha sensível de cancelamentos de vendas/produtos
 * 
 * @property int $CODIGO (PK)
 * @property int $COD_USUARIO
 * @property int $COD_CLIENTE
 * @property string $TIPO_CANCELAMENTO
 * @property float $VALOR
 * @property Carbon $DATA_CANCELAMENTO
 * @property string $MOTIVO
 */
class CancelamentoLog extends Model
{
    protected $connection = 'firebird_prod';
    protected $table = 'LOG_CANCELAMENTO';
    public $timestamps = false;
    
    protected $primaryKey = 'CODIGO';
    public $incrementing = false;
    protected $keyType = 'int';
    
    protected $casts = [
        'CODIGO' => 'int',
        'COD_USUARIO' => 'int',
        'COD_CLIENTE' => 'int',
        'VALOR' => 'float',
        'DATA_CANCELAMENTO' => 'datetime',
    ];
    
    protected $guarded = [];
    
    // ============ SCOPES ============
    
    /**
     * Apenas cancelamentos de serviço
     */
    public function scopeServicos($query)
    {
        return $query->where('TIPO_CANCELAMENTO', 'Serviço');
    }
    
    /**
     * Apenas cancelamentos de produto
     */
    public function scopeProdutos($query)
    {
        return $query->where('TIPO_CANCELAMENTO', 'Produto');
    }
    
    /**
     * Filtrar por usuário que cancelou
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('COD_USUARIO', $userId);
    }
    
    /**
     * Filtrar por cliente afetado
     */
    public function scopeByClient($query, $clientId)
    {
        return $query->where('COD_CLIENTE', $clientId);
    }
    
    /**
     * Cancelamentos acima de um valor
     */
    public function scopeAboveValue($query, $minValue)
    {
        return $query->where('VALOR', '>=', $minValue);
    }
    
    /**
     * Cancelamentos em um período
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where('DATA_CANCELAMENTO', '>=', $startDate)
            ->where('DATA_CANCELAMENTO', '<=', $endDate);
    }
    
    /**
     * Registros recentes primeiro
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('CODIGO', 'desc');
    }
    
    /**
     * Cursor pagination
     */
    public function scopeCursorPaginate($query, $lastId = 0, $limit = 50)
    {
        return $query->where('CODIGO', '>', $lastId)
            ->orderBy('CODIGO', 'desc')
            ->limit($limit);
    }
    
    // ============ RELATIONSHIPS ============
    
    /**
     * Usuário que realizou o cancelamento
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'COD_USUARIO', 'CODIGO');
    }
    
    /**
     * Cliente afetado
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'COD_CLIENTE', 'CODIGO');
    }
    
    // ============ ACCESSORS ============
    
    /**
     * Formatar valor estornado
     */
    public function getFormattedValueAttribute()
    {
        return 'R$ ' . number_format($this->VALOR, 2, ',', '.');
    }
    
    /**
     * Severidade visual (sempre critical)
     */
    public function getSeverityAttribute()
    {
        return 'danger'; // Cancelamentos sempre são críticos
    }
    
    /**
     * Badge de tipo cancelamento
     */
    public function getTypeBadgeAttribute()
    {
        return match($this->TIPO_CANCELAMENTO) {
            'Serviço' => 'blue',
            'Produto' => 'orange',
            default => 'gray',
        };
    }
    
    /**
     * Timestamp humanizado
     */
    public function getHumanReadableTimeAttribute()
    {
        return $this->DATA_CANCELAMENTO ? $this->DATA_CANCELAMENTO->diffForHumans() : 'N/A';
    }
}
