<?php

namespace App\DTOs;

use Illuminate\Support\Carbon;

/**
 * FilterDTO
 * 
 * Data Transfer Object para parâmetros de filtro de logs.
 * Encapsula critérios de filtro de forma type-safe.
 */
class FilterDTO
{
    public function __construct(
        public ?array $types = null, // ['system', 'caixa', 'cancelamento']
        public ?array $operations = null, // ['VISUALIZAR', 'SALVAR', 'EXCLUIR']
        public ?array $modules = null, // ['Ficha do Profissional', 'Caixa', ...]
        public ?array $severities = null, // ['danger', 'warning', 'success']
        public ?array $users = null, // [COD_USUARIO, ...]
        public ?string $searchText = null, // Busca em actor, action, relatedRecord
        public ?Carbon $dateFrom = null,
        public ?Carbon $dateTo = null,
        public ?float $valueMin = null,
        public ?float $valueMax = null,
        public ?string $sortBy = 'timestamp', // timestamp, value, actor
        public string $sortOrder = 'desc', // desc, asc
        public int $perPage = 50,
        public ?int $lastId = null, // Para cursor pagination
    ) {}

    /**
     * Cria DTO a partir de query parameters
     */
    public static function fromRequest(array $query): self
    {
        return new self(
            types: isset($query['types']) ? explode(',', $query['types']) : null,
            operations: isset($query['operations']) ? explode(',', $query['operations']) : null,
            modules: isset($query['modules']) ? explode(',', $query['modules']) : null,
            severities: isset($query['severities']) ? explode(',', $query['severities']) : null,
            users: isset($query['users']) ? array_map('intval', explode(',', $query['users'])) : null,
            searchText: $query['search'] ?? null,
            dateFrom: isset($query['date_from']) ? Carbon::parse($query['date_from']) : null,
            dateTo: isset($query['date_to']) ? Carbon::parse($query['date_to']) : null,
            valueMin: isset($query['value_min']) ? (float)$query['value_min'] : null,
            valueMax: isset($query['value_max']) ? (float)$query['value_max'] : null,
            sortBy: $query['sort_by'] ?? 'timestamp',
            sortOrder: $query['sort_order'] ?? 'desc',
            perPage: (int)($query['per_page'] ?? 50),
            lastId: isset($query['last_id']) ? (int)$query['last_id'] : null,
        );
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'types' => $this->types,
            'operations' => $this->operations,
            'modules' => $this->modules,
            'severities' => $this->severities,
            'users' => $this->users,
            'searchText' => $this->searchText,
            'dateFrom' => $this->dateFrom?->toIso8601String(),
            'dateTo' => $this->dateTo?->toIso8601String(),
            'valueMin' => $this->valueMin,
            'valueMax' => $this->valueMax,
            'sortBy' => $this->sortBy,
            'sortOrder' => $this->sortOrder,
            'perPage' => $this->perPage,
            'lastId' => $this->lastId,
        ];
    }

    /**
     * Verifica se há filtros ativos
     */
    public function hasFilters(): bool
    {
        return !empty($this->types)
            || !empty($this->operations)
            || !empty($this->modules)
            || !empty($this->severities)
            || !empty($this->users)
            || !empty($this->searchText)
            || !empty($this->dateFrom)
            || !empty($this->dateTo)
            || !empty($this->valueMin)
            || !empty($this->valueMax);
    }

    /**
     * Obtém chave para cache baseada nos filtros
     */
    public function getCacheKey(): string
    {
        $hash = md5(json_encode($this->toArray()));
        return "logs_filter_{$hash}";
    }
}
