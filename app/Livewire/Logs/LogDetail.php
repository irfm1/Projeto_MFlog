<?php

namespace App\Livewire\Logs;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * LogDetail Modal
 * 
 * Exibe details completos de um log em modal.
 */
class LogDetail extends Component
{
    public ?object $log = null;
    public bool $showModal = false;

    /**
     * Listener para evento log-selected
     */
    #[On('log-selected')]
    public function loadLog(string $type, int $id): void
    {
        if ($type === 'system') {
            $row = DB::table('fato_logs_sistema as f')
                ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
                ->leftJoin('dim_modulo as m', 'm.modulo_id', '=', 'f.modulo_id')
                ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
                ->where('f.log_id', $id)
                ->first([
                    'f.log_id',
                    'f.data_key',
                    'f.hora_evento',
                    'f.descricao',
                    'f.nome_movimentado',
                    'f.codigo_movimentado',
                    'f.valor_movimentado',
                    'u.nome as usuario_nome',
                    'm.nome as modulo_nome',
                    't.tipo_descricao',
                ]);

            if ($row) {
                $ts = $this->buildTimestamp((int) $row->data_key, (string) $row->hora_evento);
                $this->log = $this->buildViewLog(
                    id: (int) $row->log_id,
                    type: 'system',
                    actorName: (string) ($row->usuario_nome ?? 'Sistema'),
                    action: (string) ($row->descricao ?: 'Ação de sistema'),
                    moduleName: (string) ($row->modulo_nome ?? 'Sistema'),
                    operationType: (string) ($row->tipo_descricao ?? 'OUTRA'),
                    timestamp: $ts,
                    value: $row->valor_movimentado !== null ? (float) $row->valor_movimentado : null,
                    relatedName: (string) ($row->nome_movimentado ?? ''),
                    relatedUrl: $row->codigo_movimentado ? '/registro/' . (int) $row->codigo_movimentado : '',
                    tags: ['system']
                );
                $this->showModal = true;
            }

            return;
        }

        $row = DB::table('fato_logs_financeiro as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_cliente as c', 'c.cliente_id', '=', 'f.cliente_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.log_financeiro_id', $id)
            ->first([
                'f.log_financeiro_id',
                'f.tipo_movimento',
                'f.codigo_venda',
                'f.data_key',
                'f.hora_evento',
                'f.descricao',
                'f.valor_movimento',
                'f.valor_cancelamento',
                'u.nome as usuario_nome',
                'c.nome as cliente_nome',
                't.tipo_descricao',
            ]);

        if (!$row) {
            return;
        }

        $ts = $this->buildTimestamp((int) $row->data_key, (string) $row->hora_evento);
        $isCancel = ($row->tipo_movimento ?? 'CAIXA') === 'CANCELAMENTO';

        $sale = null;
        if (!$isCancel && !empty($row->codigo_venda)) {
            $items = DB::table('fato_venda_itens')
                ->where('log_financeiro_id', (int) $row->log_financeiro_id)
                ->orderBy('venda_item_id')
                ->get([
                    'tipo_item',
                    'nome_item',
                    'quantidade',
                    'valor_total',
                ])
                ->map(fn (object $item) => [
                    'tipo' => $this->normalizeUtf8((string) ($item->tipo_item ?? 'OUTRO')),
                    'nome' => $this->normalizeUtf8((string) ($item->nome_item ?? 'Item')),
                    'quantidade' => (float) ($item->quantidade ?? 0),
                    'valor_total' => (float) ($item->valor_total ?? 0),
                ])
                ->all();

            $sale = [
                'numero' => (int) $row->codigo_venda,
                'cliente' => $this->normalizeUtf8((string) ($row->cliente_nome ?? 'SEM INFORMAÇÃO')),
                'itens' => $items,
            ];
        }

        $this->log = $this->buildViewLog(
            id: (int) $row->log_financeiro_id,
            type: $isCancel ? 'cancelamento' : 'caixa',
            actorName: (string) ($row->usuario_nome ?? 'Sistema'),
            action: (string) ($row->descricao ?: ($isCancel ? 'Cancelamento' : 'Movimento de caixa')),
            moduleName: $isCancel ? 'Cancelamentos' : 'Caixa',
            operationType: (string) ($row->tipo_descricao ?? ($isCancel ? 'CANCELAMENTO' : 'CAIXA')),
            timestamp: $ts,
            value: $isCancel ? (float) ($row->valor_cancelamento ?? 0) : (float) ($row->valor_movimento ?? 0),
            relatedName: $isCancel ? 'Cancelamento' : 'Movimento de Caixa',
            relatedUrl: '',
            tags: [$isCancel ? 'cancelamento' : 'caixa'],
            sale: $sale
        );
        $this->showModal = true;
    }

    private function buildTimestamp(int $dataKey, string $time): Carbon
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
        array $tags,
        ?array $sale = null
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
            'sale' => $sale,
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

    public function close(): void
    {
        $this->showModal = false;
        $this->log = null;
    }

    public function render()
    {
        return view('livewire.logs.log-detail');
    }
}
