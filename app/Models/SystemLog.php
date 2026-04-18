<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * SystemLog - Auditoria de Ações do Sistema
 * 
 * Representa registros de USUARIOS_LOG do Firebird
 * Total: 117,598 registros
 * 
 * @property int $CODIGO (PK)
 * @property int $CODIGO_USUARIO
 * @property string $TIPO_OPERACAO
 * @property string $MODULO
 * @property int $CODIGO_MOVIMENTADO
 * @property string $NOME_MOVIMENTADO
 * @property string $DESCRICAO
 * @property Carbon $DATA
 * @property string $HORA
 */
class SystemLog extends Model
{
    protected $connection = 'firebird_prod';
    protected $table = 'USUARIOS_LOG';
    public $timestamps = false;
    
    protected $primaryKey = 'CODIGO';
    public $incrementing = false;
    protected $keyType = 'int';
    
    protected $casts = [
        'CODIGO' => 'int',
        'CODIGO_USUARIO' => 'int',
        'CODIGO_USUARIO_EXECUTOU' => 'int',
        'CODIGO_MOVIMENTADO' => 'int',
        'DATA' => 'date',
    ];
    
    protected $guarded = [];
    
    // ============ SCOPES ============
    
    /**
     * Filtrar por tipo de operação
     */
    public function scopeByOperation($query, $operation)
    {
        return $query->where('TIPO_OPERACAO', $operation);
    }
    
    /**
     * Filtrar por módulo
     */
    public function scopeByModule($query, $module)
    {
        return $query->where('MODULO', $module);
    }
    
    /**
     * Filtrar por usuário
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('CODIGO_USUARIO', $userId);
    }
    
    /**
     * Filtrar por data
     */
    public function scopeByDate($query, $date)
    {
        return $query->where('DATA', '=', $date);
    }
    
    /**
     * Registros recentes primeiro
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('CODIGO', 'desc');
    }
    
    /**
     * Cursor pagination (mais eficiente que offset)
     */
    public function scopeCursorPaginate($query, $lastId = 0, $limit = 50)
    {
        return $query->where('CODIGO', '>', $lastId)
            ->orderBy('CODIGO', 'desc')
            ->limit($limit);
    }
    
    // ============ RELATIONSHIPS ============
    
    /**
     * Relacionamento com usuário que realizou a ação
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'CODIGO_USUARIO', 'CODIGO');
    }
    
    /**
     * Relacionamento com profissional (quando aplicável)
     * Apenas quando MODULO = 'Ficha do Profissional'
     */
    public function professional()
    {
        return $this->belongsTo(Professional::class, 'CODIGO_MOVIMENTADO', 'CODIGO')
            ->where('MODULO', 'Ficha do Profissional');
    }
    
    /**
     * Relacionamento com cliente (quando aplicável)
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'CODIGO_MOVIMENTADO', 'CODIGO')
            ->where('MODULO', 'Cadastro de Cliente');
    }
    
    // ============ ACCESSORS ============
    
    /**
     * Combinar DATA e HORA em um timestamp
     */
    public function getFullTimestampAttribute()
    {
        if ($this->DATA && $this->HORA) {
            return Carbon::createFromFormat('Y-m-d H:i:s', "{$this->DATA} {$this->HORA}");
        }
        return null;
    }
    
    /**
     * Timestamp humanizado (ex: "Há 2 horas")
     */
    public function getHumanReadableTimeAttribute()
    {
        $timestamp = $this->full_timestamp;
        return $timestamp ? $timestamp->diffForHumans() : 'N/A';
    }
}
