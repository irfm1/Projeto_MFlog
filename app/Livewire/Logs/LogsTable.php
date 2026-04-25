<?php

namespace App\Livewire\Logs;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * LogsTable
 * 
 * Componente Livewire para exibição de logs com filtros, ordenação e paginação.
 * Inicialmente carrega logs dos últimos 30 dias, paginados e ordenados do mais recente para o mais antigo.
 */
class LogsTable extends Component
{
    // Filtros
    public ?array $types = null;
    public ?array $severities = null;
    public ?string $searchText = null;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    // Paginação
    public int $perPage = 15;
    public int $page = 1;

    // UI
    public ?int $selectedLogId = null;
    private const MAX_LOG_POOL = 5000;

    public function mount(): void
    {
        // Padrão: últimos 30 dias
        $today = Carbon::today();
        $this->dateTo = $today->toDateString();
        $this->dateFrom = $today->copy()->subDays(30)->toDateString();
    }

    /**
     * Listener para evento de filtros atualizados
     */
    #[On('filters-updated')]
    public function handleFiltersUpdated(
        ?array $types = null,
        ?array $severities = null,
        ?string $searchText = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): void
    {
        $this->types = $types;
        $this->severities = $severities;
        $this->searchText = $searchText;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->page = 1;
    }

    /**
     * Obtém logs para exibir com paginação
     * CACHED - não re-executa queries a cada render
     */
    #[Computed]
    public function getLogs(): array
    {
        $logs = [];
        $limit = $this->resolvePoolLimitForPage();

        if (!$this->types || in_array('system', $this->types)) {
            $logs = array_merge($logs, $this->collectSystemLogs($limit));
        }

        if (!$this->types || in_array('caixa', $this->types)) {
            $logs = array_merge($logs, $this->collectCaixaLogs($limit));
        }

        if (!$this->types || in_array('cancelamento', $this->types)) {
            $logs = array_merge($logs, $this->collectCancelamentoLogs($limit));
        }

        // Ordenar do mais recente para o mais antigo
        usort($logs, function (object $a, object $b) {
            return $b->timestamp->getTimestamp() <=> $a->timestamp->getTimestamp();
        });

        // Paginação manual
        $totalItems = count($logs);
        $totalPages = max((int) ceil($totalItems / $this->perPage), 1);

        if ($this->page > $totalPages) {
            $this->page = $totalPages;
        }

        $offset = ($this->page - 1) * $this->perPage;

        return array_slice($logs, $offset, $this->perPage);
    }

    /**
     * Total de logs para paginação
     */
    #[Computed]
    public function getTotalLogs(): int
    {
        $logs = [];
        $limit = self::MAX_LOG_POOL;

        if (!$this->types || in_array('system', $this->types)) {
            $logs = array_merge($logs, $this->collectSystemLogs($limit));
        }

        if (!$this->types || in_array('caixa', $this->types)) {
            $logs = array_merge($logs, $this->collectCaixaLogs($limit));
        }

        if (!$this->types || in_array('cancelamento', $this->types)) {
            $logs = array_merge($logs, $this->collectCancelamentoLogs($limit));
        }

        return count($logs);
    }

    /**
     * Total de páginas
     */
    #[Computed]
    public function getTotalPages(): int
    {
        return max((int) ceil($this->getTotalLogs() / $this->perPage), 1);
    }

    private function resolvePoolLimitForPage(): int
    {
        // Garante volume suficiente para paginações mais altas sem estourar memória.
        $base = ($this->page + 2) * $this->perPage;

        return min(max($base, 200), self::MAX_LOG_POOL);
    }

    private function collectSystemLogs(int $limit): array
    {
        $query = DB::table('fato_logs_sistema as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_modulo as m', 'm.modulo_id', '=', 'f.modulo_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id');

        // Filtrar por período de datas
        if ($this->dateFrom) {
            $dateFromKey = Carbon::createFromFormat('Y-m-d', $this->dateFrom)->format('Ymd');
            $query->where('f.data_key', '>=', $dateFromKey);
        }
        if ($this->dateTo) {
            $dateToKey = Carbon::createFromFormat('Y-m-d', $this->dateTo)->format('Ymd');
            $query->where('f.data_key', '<=', $dateToKey);
        }

        $rows = $query
            ->orderByDesc('f.data_key')
            ->orderByDesc('f.hora_evento')
            ->limit($limit)
            ->get([
                'f.log_id',
                'f.hora_evento',
                'f.data_key',
                'f.descricao',
                'f.nome_movimentado',
                'f.codigo_movimentado',
                'f.valor_movimentado',
                'u.nome as usuario_nome',
                'm.nome as modulo_nome',
                't.tipo_descricao',
            ]);

        $logs = [];
        foreach ($rows as $row) {
            try {
                $timestamp = $this->buildTimestampFromDataKeyAndTime((int) $row->data_key, (string) $row->hora_evento);
                $log = $this->buildViewLog(
                    id: (int) $row->log_id,
                    type: 'system',
                    actorName: (string) ($row->usuario_nome ?? 'Sistema'),
                    action: (string) ($row->descricao ?: 'Ação de sistema'),
                    moduleName: (string) ($row->modulo_nome ?? 'Sistema'),
                    operationType: (string) ($row->tipo_descricao ?? 'OUTRA'),
                    timestamp: $timestamp,
                    value: $row->valor_movimentado !== null ? (float) $row->valor_movimentado : null,
                    relatedName: (string) ($row->nome_movimentado ?? ''),
                    relatedUrl: $row->codigo_movimentado ? '/registro/' . (int) $row->codigo_movimentado : '',
                    tags: ['system']
                );

                if ($this->searchText && !$this->textMatches($log, $this->searchText)) {
                    continue;
                }

                if ($this->matchesSeverityFilter($log)) {
                    $logs[] = $log;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $logs;
    }

    private function collectCaixaLogs(int $limit): array
    {
        $query = DB::table('fato_logs_financeiro as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.tipo_movimento', 'CAIXA');

        // Filtrar por período de datas
        if ($this->dateFrom) {
            $dateFromKey = Carbon::createFromFormat('Y-m-d', $this->dateFrom)->format('Ymd');
            $query->where('f.data_key', '>=', $dateFromKey);
        }
        if ($this->dateTo) {
            $dateToKey = Carbon::createFromFormat('Y-m-d', $this->dateTo)->format('Ymd');
            $query->where('f.data_key', '<=', $dateToKey);
        }

        $rows = $query
            ->orderByDesc('f.data_key')
            ->orderByDesc('f.hora_evento')
            ->limit($limit)
            ->get([
                'f.log_financeiro_id',
                'f.hora_evento',
                'f.data_key',
                'f.descricao',
                'f.valor_movimento',
                'u.nome as usuario_nome',
                't.tipo_descricao',
            ]);

        $logs = [];
        foreach ($rows as $row) {
            try {
                $timestamp = $this->buildTimestampFromDataKeyAndTime((int) $row->data_key, (string) $row->hora_evento);
                $log = $this->buildViewLog(
                    id: (int) $row->log_financeiro_id,
                    type: 'caixa',
                    actorName: (string) ($row->usuario_nome ?? 'Sistema'),
                    action: (string) ($row->descricao ?: 'Movimento de caixa'),
                    moduleName: 'Caixa',
                    operationType: (string) ($row->tipo_descricao ?? 'CAIXA'),
                    timestamp: $timestamp,
                    value: $row->valor_movimento !== null ? (float) $row->valor_movimento : 0.0,
                    relatedName: 'Movimento de Caixa',
                    relatedUrl: '',
                    tags: ['caixa']
                );

                if ($this->searchText && !$this->textMatches($log, $this->searchText)) {
                    continue;
                }

                if ($this->matchesSeverityFilter($log)) {
                    $logs[] = $log;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $logs;
    }

    private function collectCancelamentoLogs(int $limit): array
    {
        $query = DB::table('fato_logs_financeiro as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.tipo_movimento', 'CANCELAMENTO');

        // Filtrar por período de datas
        if ($this->dateFrom) {
            $dateFromKey = Carbon::createFromFormat('Y-m-d', $this->dateFrom)->format('Ymd');
            $query->where('f.data_key', '>=', $dateFromKey);
        }
        if ($this->dateTo) {
            $dateToKey = Carbon::createFromFormat('Y-m-d', $this->dateTo)->format('Ymd');
            $query->where('f.data_key', '<=', $dateToKey);
        }

        $rows = $query
            ->orderByDesc('f.data_key')
            ->orderByDesc('f.hora_evento')
            ->limit($limit)
            ->get([
                'f.log_financeiro_id',
                'f.hora_evento',
                'f.data_key',
                'f.descricao',
                'f.valor_cancelamento',
                'u.nome as usuario_nome',
                't.tipo_descricao',
            ]);

        $logs = [];
        foreach ($rows as $row) {
            try {
                $timestamp = $this->buildTimestampFromDataKeyAndTime((int) $row->data_key, (string) $row->hora_evento);
                $log = $this->buildViewLog(
                    id: (int) $row->log_financeiro_id,
                    type: 'cancelamento',
                    actorName: (string) ($row->usuario_nome ?? 'Sistema'),
                    action: (string) ($row->descricao ?: 'Cancelamento'),
                    moduleName: 'Cancelamentos',
                    operationType: (string) ($row->tipo_descricao ?? 'CANCELAMENTO'),
                    timestamp: $timestamp,
                    value: $row->valor_cancelamento !== null ? (float) $row->valor_cancelamento : 0.0,
                    relatedName: 'Cancelamento',
                    relatedUrl: '',
                    tags: ['cancelamento']
                );

                if ($this->searchText && !$this->textMatches($log, $this->searchText)) {
                    continue;
                }

                if ($this->matchesSeverityFilter($log)) {
                    $logs[] = $log;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $logs;
    }

    private function matchesSeverityFilter(object $log): bool
    {
        if (!$this->severities) {
            return true;
        }

        return in_array($log->severity, $this->severities);
    }

    /**
     * Filtra texto na aplicação (mais rápido que LIKE no Firebird)
     */
    private function textMatches(object $log, string $searchText): bool
    {
        $search = strtolower($searchText);

        return stripos($log->action ?? '', $search) !== false ||
               stripos($log->actor['name'] ?? '', $search) !== false ||
               stripos($log->module['name'] ?? '', $search) !== false;
    }

    private function buildTimestampFromDataKeyAndTime(int $dataKey, string $time): Carbon
    {
        $date = Carbon::createFromFormat('Ymd', (string) $dataKey);
        $safeTime = preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) ? $time : '00:00:00';
        return Carbon::parse($date->format('Y-m-d') . ' ' . $safeTime);
    }

    private function buildViewLog(
        int $id,
        string $type,
        string $actorName,
        string $action,
        string $moduleName,
        string $operationType,
        Carbon $timestamp,
        ?float $value,
        string $relatedName,
        string $relatedUrl,
        array $tags
    ): object {
        $actorName = $this->normalizeUtf8($actorName);
        $action = $this->normalizeUtf8($action);
        $moduleName = $this->normalizeUtf8($moduleName);
        $operationType = $this->normalizeUtf8($operationType);
        $relatedName = $this->normalizeUtf8($relatedName);
        $tags = array_map(fn (string $tag) => $this->normalizeUtf8($tag), $tags);

        $severity = match ($type) {
            'cancelamento' => 'danger',
            'caixa' => ($value ?? 0) >= 0 ? 'success' : 'warning',
            default => 'info',
        };

        $moduleIcon = match (strtolower($moduleName)) {
            'caixa' => 'wallet-2',
            'cancelamentos' => 'x-circle',
            default => 'file-text',
        };

        return (object) [
            'id' => $id,
            'type' => $type,
            'actor' => [
                'name' => $actorName,
                'avatar' => null,
            ],
            'action' => $action,
            'module' => [
                'name' => $moduleName,
                'icon' => $moduleIcon,
            ],
            'operationType' => $operationType,
            'value' => $value !== null ? [
                'formatted' => 'R$ ' . number_format(abs($value), 2, ',', '.'),
                'currency' => 'BRL',
            ] : null,
            'relatedRecord' => [
                'name' => $relatedName,
                'url' => $relatedUrl,
            ],
            'tags' => $tags,
            'timestamp' => $timestamp,
            'humanReadableTime' => $timestamp->diffForHumans(),
            'severity' => $severity,
        ];
    }

    private function normalizeUtf8(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    }

    /**
     * Select log para detalhe
     */
    public function selectLog(string $type, int $logId): void
    {
        $this->selectedLogId = $logId;
        // Despachar evento com payload nomeado para o modal.
        $this->dispatch('log-selected', type: $type, id: $logId);
    }

    /**
     * Mudar página
     */
    public function goToPage(int $page): void
    {
        if ($page < 1 || $page > $this->getTotalPages()) {
            return;
        }
        $this->page = $page;
    }

    /**
     * Página anterior
     */
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    /**
     * Próxima página
     */
    public function nextPage(): void
    {
        if ($this->page < $this->getTotalPages()) {
            $this->page++;
        }
    }

    public function render()
    {
        return view('livewire.logs.logs-table', [
            'logs' => $this->getLogs(),
            'totalLogs' => $this->getTotalLogs(),
            'totalPages' => $this->getTotalPages(),
        ]);
    }
}
