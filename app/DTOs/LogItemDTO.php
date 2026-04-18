<?php

namespace App\DTOs;

use Illuminate\Support\Carbon;

/**
 * LogItemDTO
 * 
 * Data Transfer Object para representar um item de log unificado.
 * Transforma logs brutos de diferentes tabelas em um formato humanizado e consistente.
 */
class LogItemDTO
{
    /**
     * Construtor
     * 
     * @param int $id ID único do log
     * @param string $type Tipo: 'system', 'caixa', 'cancelamento'
     * @param string $actorName Nome de quem realizou a ação
     * @param string $actorAvatar URL do avatar do ator
     * @param string $action Ação humanizada (ex: "alterou", "realizou venda")
     * @param string $relatedRecordName Nome do registro relacionado
     * @param string $relatedRecordUrl URL do registro relacionado
     * @param string $module Módulo do sistema (ex: "Ficha do Profissional")
     * @param string $moduleIcon Ícone do módulo
     * @param string|null $operationType Tipo de operação (ex: "VISUALIZAR", "SALVAR")
     * @param float|null $value Valor monetário (se aplicável)
     * @param string|null $valueCurrency Moeda (ex: "BRL")
     * @param array $tags Tags para filtro/categorização
     * @param Carbon $timestamp Data e hora do evento
     * @param string $humanReadableTime Tempo humanizado (ex: "Há 2 minutos")
     * @param string $severity Severidade: 'info', 'success', 'warning', 'danger'
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $actorName,
        public string $actorAvatar,
        public string $action,
        public string $relatedRecordName,
        public string $relatedRecordUrl,
        public string $module,
        public string $moduleIcon,
        public ?string $operationType,
        public ?float $value,
        public ?string $valueCurrency,
        public array $tags,
        public Carbon $timestamp,
        public string $humanReadableTime,
        public string $severity,
    ) {}

    /**
     * Cria DTO a partir de array de dados
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            actorName: $data['actorName'],
            actorAvatar: $data['actorAvatar'],
            action: $data['action'],
            relatedRecordName: $data['relatedRecordName'],
            relatedRecordUrl: $data['relatedRecordUrl'],
            module: $data['module'],
            moduleIcon: $data['moduleIcon'],
            operationType: $data['operationType'] ?? null,
            value: $data['value'] ?? null,
            valueCurrency: $data['valueCurrency'] ?? null,
            tags: $data['tags'] ?? [],
            timestamp: Carbon::parse($data['timestamp']),
            humanReadableTime: $data['humanReadableTime'],
            severity: $data['severity'],
        );
    }

    /**
     * Converte DTO para array (para JSON ou cache)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'actor' => [
                'name' => $this->actorName,
                'avatar' => $this->actorAvatar,
            ],
            'action' => $this->action,
            'relatedRecord' => [
                'name' => $this->relatedRecordName,
                'url' => $this->relatedRecordUrl,
            ],
            'module' => $this->module,
            'moduleIcon' => $this->moduleIcon,
            'operationType' => $this->operationType,
            'value' => $this->value,
            'valueCurrency' => $this->valueCurrency,
            'tags' => $this->tags,
            'timestamp' => $this->timestamp->toIso8601String(),
            'humanReadableTime' => $this->humanReadableTime,
            'severity' => $this->severity,
        ];
    }

    /**
     * Serializa para JSON (para broadcast/cache)
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Obtém representação em string
     */
    public function __toString(): string
    {
        return "{$this->actorName}: {$this->action} {$this->relatedRecordName}";
    }
}
