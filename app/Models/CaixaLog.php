<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * CaixaLog - Movimentações Financeiras
 * 
 * Representa registros de LOG_CAIXA do Firebird
 * Total: 48,992 registros
 * 
 * Rastreia: aberturas, vendas, fechamentos de terminais
 * 
 * @property int $CODIGO (PK)
 * @property int $COD_USUARIO
 * @property int $COD_CAIXA
 * @property string $NOME_PC
 * @property float $VALOR_MOVIMENTADO
 * @property string $DESCRICAO
 * @property Carbon $DATA_HORA
 */
class CaixaLog extends Model
{
    protected $connection = 'firebird_prod';
    protected $table = 'LOG_CAIXA';
    public $timestamps = false;
    
    protected $primaryKey = 'CODIGO';
    public $incrementing = false;
    protected $keyType = 'int';
    
    protected $casts = [
        'CODIGO' => 'int',
        'COD_USUARIO' => 'int',
        'COD_CAIXA' => 'int',
        'VALOR_MOVIMENTADO' => 'float',
        'DATA_HORA' => 'datetime',
    ];
    
    protected $guarded = [];
    
    // ============ SCOPES ============
    
    /**
     * Filtrar por usuário
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('COD_USUARIO', $userId);
    }
    
    /**
     * Filtrar por terminal/caixa
     */
    public function scopeByTerminal($query, $terminalCode)
    {
        return $query->where('NOME_PC', 'like', "%$terminalCode%");
    }
    
    /**
     * Apenas vendas (valor positivo ou contém "VENDA")
     */
    public function scopeVendas($query)
    {
        return $query->where('DESCRICAO', 'like', '%VENDA%');
    }
    
    /**
     * Apenas aberturas de caixa
     */
    public function scopeAberturas($query)
    {
        return $query->where('DESCRICAO', 'like', '%ABERTURA%');
    }
    
    /**
     * Apenas fechamentos
     */
    public function scopeFechamentos($query)
    {
        return $query->where('DESCRICAO', 'like', '%FECHAMENTO%');
    }
    
    /**
     * Movimentações dentro de um período
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where('DATA_HORA', '>=', $startDate)
            ->where('DATA_HORA', '<=', $endDate);
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
     * Usuário que realizou a movimentação
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'COD_USUARIO', 'CODIGO');
    }
    
    /**
     * Venda relacionada (extraída de DESCRICAO via parsing)
     */
    public function sale()
    {
        // Extraído via service de parsing
        // $saleNumber = LogParserService::extractSaleNumber($this->DESCRICAO)
        // $this->belongsTo(Sale::class, 'sale_number', 'CODIGO')
    }
    
    // ============ ACCESSORS ============
    
    /**
     * Extrair número de venda da descrição
     * Ex: "REALIZOU UMA VENDA Nº: 89340" → 89340
     */
    public function getExtractedSaleNumberAttribute()
    {
        $pattern = '/venda\s*nº?\s*:?\s*(\d+)/i';
        if (preg_match($pattern, $this->DESCRICAO, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
    
    /**
     * Formatar valor em BRL
     */
    public function getFormattedValueAttribute()
    {
        return 'R$ ' . number_format($this->VALOR_MOVIMENTADO, 2, ',', '.');
    }
    
    /**
     * Cor visual baseada em valor positivo/negativo
     */
    public function getSeverityAttribute()
    {
        if (is_null($this->VALOR_MOVIMENTADO)) {
            return 'info';
        }
        return $this->VALOR_MOVIMENTADO > 0 ? 'success' : 'danger';
    }
    
    /**
     * Timestamp humanizado
     */
    public function getHumanReadableTimeAttribute()
    {
        return $this->DATA_HORA ? $this->DATA_HORA->diffForHumans() : 'N/A';
    }
}
