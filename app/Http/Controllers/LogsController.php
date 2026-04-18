<?php

namespace App\Http\Controllers;

use App\DTOs\FilterDTO;
use App\DTOs\LogItemDTO;
use App\Services\LogFormatterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LogsController
 * 
 * Endpoints da API para recuperar e filtrar logs.
 * Retorna DTOs em formato JSON para frontend/WebSocket.
 * 
 * Nunca escreve no banco (READ-ONLY)
 */
class LogsController extends Controller
{
    public function __construct(
        private LogFormatterService $formatter,
    ) {}

    /**
     * GET /api/logs
     * 
     * Lista logs recentes com filtros opcionais.
     * Suporta paginação com cursor.
     */
    public function index(Request $request): JsonResponse
    {
        $filter = FilterDTO::fromRequest($request->query());

        try {
            // Função auxiliar para coletar logs de todas as tabelas
            $logs = $this->collectLogs($filter);

            // Formata para JSON
            $formatted = $this->formatter->collectionToJson(
                collect($logs)
                    ->sortByDesc(fn (LogItemDTO $log) => $log->timestamp)
                    ->values()
            );

            return response()->json([
                'status' => 'success',
                'data' => $formatted,
                'count' => count($formatted),
                'filters' => $filter->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/logs/system
     * 
     * Apenas logs de auditoria do sistema
     */
    public function system(Request $request): JsonResponse
    {
        $filter = FilterDTO::fromRequest($request->query());
        $filter->types = ['system'];

        $logs = $this->collectSystemLogs($filter);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatter->collectionToJson(collect($logs)),
            'count' => count($logs),
        ]);
    }

    /**
     * GET /api/logs/caixa
     * 
     * Apenas transações de caixa
     */
    public function caixa(Request $request): JsonResponse
    {
        $filter = FilterDTO::fromRequest($request->query());
        $filter->types = ['caixa'];

        $logs = $this->collectCaixaLogs($filter);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatter->collectionToJson(collect($logs)),
            'count' => count($logs),
        ]);
    }

    /**
     * GET /api/logs/cancelamento
     * 
     * Apenas cancelamentos (críticos)
     */
    public function cancelamento(Request $request): JsonResponse
    {
        $filter = FilterDTO::fromRequest($request->query());
        $filter->types = ['cancelamento'];

        $logs = $this->collectCancelamentoLogs($filter);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatter->collectionToJson(collect($logs)),
            'count' => count($logs),
        ]);
    }

    /**
     * GET /api/logs/{id}
     * 
     * Detalhe de um log específico
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Tenta encontrar em qualquer tabela
            $log = $this->findLogById($id);

            if (!$log) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Log não encontrado',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $log->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/logs/export/csv
     * 
     * Exporta logs em CSV
     */
    public function exportCsv(Request $request)
    {
        $filter = FilterDTO::fromRequest($request->query());

        try {
            $logs = $this->collectLogs($filter);
            $formatted = $this->formatter->collectionToCsv(collect($logs));

            // Gera CSV
            $filename = 'logs_' . now()->format('Y-m-d_H-i-s') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($formatted) {
                $file = fopen('php://output', 'w');
                
                // Headers
                fputcsv($file, $formatted['headers']);
                
                // Rows
                foreach ($formatted['rows'] as $row) {
                    fputcsv($file, $row);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/logs/stats
     * 
     * Estatísticas de logs
     */
    public function stats(): JsonResponse
    {
        try {
            $systemCount = DB::table('fato_logs_sistema')->count();
            $caixaCount = DB::table('fato_logs_financeiro')->where('tipo_movimento', 'CAIXA')->count();
            $cancelamentoCount = DB::table('fato_logs_financeiro')->where('tipo_movimento', 'CANCELAMENTO')->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'system_logs' => $systemCount,
                    'caixa_logs' => $caixaCount,
                    'cancelamento_logs' => $cancelamentoCount,
                    'total' => $systemCount + $caixaCount + $cancelamentoCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Coleta logs de todas as tabelas
     */
    private function collectLogs(FilterDTO $filter): array
    {
        $logs = [];

        if (!$filter->types || in_array('system', $filter->types ?? [])) {
            $logs = array_merge($logs, $this->collectSystemLogs($filter));
        }

        if (!$filter->types || in_array('caixa', $filter->types ?? [])) {
            $logs = array_merge($logs, $this->collectCaixaLogs($filter));
        }

        if (!$filter->types || in_array('cancelamento', $filter->types ?? [])) {
            $logs = array_merge($logs, $this->collectCancelamentoLogs($filter));
        }

        return $logs;
    }

    private function collectSystemLogs(FilterDTO $filter): array
    {
        $query = DB::table('fato_logs_sistema as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_modulo as m', 'm.modulo_id', '=', 'f.modulo_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->select([
                'f.log_id',
                'f.data_key',
                'f.hora_evento',
                'f.descricao',
                'f.codigo_movimentado',
                'f.nome_movimentado',
                'f.valor_movimentado',
                'u.nome as usuario_nome',
                'u.codigo_usuario',
                'm.nome as modulo_nome',
                't.tipo_descricao',
            ]);

        if ($filter->users) {
            $query->whereIn('u.codigo_usuario', $filter->users);
        }
        if ($filter->operations) {
            $query->whereIn('t.tipo_descricao', $filter->operations);
        }
        if ($filter->modules) {
            $query->whereIn('m.nome', $filter->modules);
        }
        if ($filter->dateFrom) {
            $query->where('f.data_key', '>=', (int) $filter->dateFrom->format('Ymd'));
        }
        if ($filter->dateTo) {
            $query->where('f.data_key', '<=', (int) $filter->dateTo->format('Ymd'));
        }
        if ($filter->searchText) {
            $text = '%' . $filter->searchText . '%';
            $query->where(function ($q) use ($text) {
                $q->where('f.descricao', 'like', $text)
                    ->orWhere('u.nome', 'like', $text)
                    ->orWhere('m.nome', 'like', $text)
                    ->orWhere('f.nome_movimentado', 'like', $text);
            });
        }

        $rows = $query->orderBy('f.data_key', $filter->sortOrder === 'asc' ? 'asc' : 'desc')
            ->orderBy('f.hora_evento', $filter->sortOrder === 'asc' ? 'asc' : 'desc')
            ->limit($filter->perPage)
            ->get();

        $logs = [];
        foreach ($rows as $row) {
            $logs[] = $this->makeSystemDto($row);
        }

        return $logs;
    }

    private function collectCaixaLogs(FilterDTO $filter): array
    {
        $query = DB::table('fato_logs_financeiro as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.tipo_movimento', 'CAIXA')
            ->select([
                'f.log_financeiro_id',
                'f.data_key',
                'f.hora_evento',
                'f.descricao',
                'f.valor_movimento',
                'u.nome as usuario_nome',
                'u.codigo_usuario',
                't.tipo_descricao',
            ]);

        if ($filter->users) {
            $query->whereIn('u.codigo_usuario', $filter->users);
        }
        if ($filter->dateFrom) {
            $query->where('f.data_key', '>=', (int) $filter->dateFrom->format('Ymd'));
        }
        if ($filter->dateTo) {
            $query->where('f.data_key', '<=', (int) $filter->dateTo->format('Ymd'));
        }
        if ($filter->valueMin !== null) {
            $query->where('f.valor_movimento', '>=', $filter->valueMin);
        }
        if ($filter->valueMax !== null) {
            $query->where('f.valor_movimento', '<=', $filter->valueMax);
        }
        if ($filter->searchText) {
            $text = '%' . $filter->searchText . '%';
            $query->where(function ($q) use ($text) {
                $q->where('f.descricao', 'like', $text)
                    ->orWhere('u.nome', 'like', $text)
                    ->orWhere('t.tipo_descricao', 'like', $text);
            });
        }

        $rows = $query->orderBy('f.data_key', $filter->sortOrder === 'asc' ? 'asc' : 'desc')
            ->orderBy('f.hora_evento', $filter->sortOrder === 'asc' ? 'asc' : 'desc')
            ->limit($filter->perPage)
            ->get();

        $logs = [];
        foreach ($rows as $row) {
            $logs[] = $this->makeCaixaDto($row);
        }

        return $logs;
    }

    private function collectCancelamentoLogs(FilterDTO $filter): array
    {
        $query = DB::table('fato_logs_financeiro as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.tipo_movimento', 'CANCELAMENTO')
            ->select([
                'f.log_financeiro_id',
                'f.data_key',
                'f.hora_evento',
                'f.descricao',
                'f.valor_cancelamento',
                'u.nome as usuario_nome',
                'u.codigo_usuario',
                't.tipo_descricao',
            ]);

        if ($filter->users) {
            $query->whereIn('u.codigo_usuario', $filter->users);
        }
        if ($filter->dateFrom) {
            $query->where('f.data_key', '>=', (int) $filter->dateFrom->format('Ymd'));
        }
        if ($filter->dateTo) {
            $query->where('f.data_key', '<=', (int) $filter->dateTo->format('Ymd'));
        }
        if ($filter->valueMin !== null) {
            $query->where('f.valor_cancelamento', '>=', $filter->valueMin);
        }
        if ($filter->valueMax !== null) {
            $query->where('f.valor_cancelamento', '<=', $filter->valueMax);
        }
        if ($filter->searchText) {
            $text = '%' . $filter->searchText . '%';
            $query->where(function ($q) use ($text) {
                $q->where('f.descricao', 'like', $text)
                    ->orWhere('u.nome', 'like', $text)
                    ->orWhere('t.tipo_descricao', 'like', $text);
            });
        }

        $rows = $query->orderBy('f.data_key', $filter->sortOrder === 'asc' ? 'asc' : 'desc')
            ->orderBy('f.hora_evento', $filter->sortOrder === 'asc' ? 'asc' : 'desc')
            ->limit($filter->perPage)
            ->get();

        $logs = [];
        foreach ($rows as $row) {
            $logs[] = $this->makeCancelamentoDto($row);
        }

        return $logs;
    }

    /**
     * Encontra um log por ID em qualquer tabela
     */
    private function findLogById(string $id): ?LogItemDTO
    {
        $row = DB::table('fato_logs_sistema as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_modulo as m', 'm.modulo_id', '=', 'f.modulo_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.log_id', (int) $id)
            ->first([
                'f.log_id',
                'f.data_key',
                'f.hora_evento',
                'f.descricao',
                'f.codigo_movimentado',
                'f.nome_movimentado',
                'f.valor_movimentado',
                'u.nome as usuario_nome',
                'm.nome as modulo_nome',
                't.tipo_descricao',
            ]);

        if ($row) {
            return $this->makeSystemDto($row);
        }

        $row = DB::table('fato_logs_financeiro as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.log_financeiro_id', (int) $id)
            ->where('f.tipo_movimento', 'CAIXA')
            ->first([
                'f.log_financeiro_id',
                'f.data_key',
                'f.hora_evento',
                'f.descricao',
                'f.valor_movimento',
                'u.nome as usuario_nome',
                't.tipo_descricao',
            ]);

        if ($row) {
            return $this->makeCaixaDto($row);
        }

        $row = DB::table('fato_logs_financeiro as f')
            ->join('dim_usuario as u', 'u.usuario_id', '=', 'f.usuario_id')
            ->leftJoin('dim_tipo_operacao as t', 't.tipo_operacao_id', '=', 'f.tipo_operacao_id')
            ->where('f.log_financeiro_id', (int) $id)
            ->where('f.tipo_movimento', 'CANCELAMENTO')
            ->first([
                'f.log_financeiro_id',
                'f.data_key',
                'f.hora_evento',
                'f.descricao',
                'f.valor_cancelamento',
                'u.nome as usuario_nome',
                't.tipo_descricao',
            ]);

        if ($row) {
            return $this->makeCancelamentoDto($row);
        }

        return null;
    }

    private function makeSystemDto(object $row): LogItemDTO
    {
        $timestamp = $this->buildTimestamp((int) $row->data_key, (string) $row->hora_evento);

        return new LogItemDTO(
            id: (int) $row->log_id,
            type: 'system',
            actorName: (string) ($row->usuario_nome ?? 'Sistema'),
            actorAvatar: '',
            action: (string) ($row->descricao ?? 'Ação de sistema'),
            relatedRecordName: (string) ($row->nome_movimentado ?? 'Registro'),
            relatedRecordUrl: $row->codigo_movimentado ? '/registro/' . (int) $row->codigo_movimentado : '',
            module: (string) ($row->modulo_nome ?? 'Sistema'),
            moduleIcon: 'file-text',
            operationType: (string) ($row->tipo_descricao ?? 'OUTRA'),
            value: $row->valor_movimentado !== null ? (float) $row->valor_movimentado : null,
            valueCurrency: 'BRL',
            tags: ['system'],
            timestamp: $timestamp,
            humanReadableTime: $timestamp->diffForHumans(),
            severity: 'info',
        );
    }

    private function makeCaixaDto(object $row): LogItemDTO
    {
        $timestamp = $this->buildTimestamp((int) $row->data_key, (string) $row->hora_evento);

        return new LogItemDTO(
            id: (int) $row->log_financeiro_id,
            type: 'caixa',
            actorName: (string) ($row->usuario_nome ?? 'Sistema'),
            actorAvatar: '',
            action: (string) ($row->descricao ?? 'Movimento de caixa'),
            relatedRecordName: 'Movimento de Caixa',
            relatedRecordUrl: '',
            module: 'Caixa',
            moduleIcon: 'wallet-2',
            operationType: (string) ($row->tipo_descricao ?? 'CAIXA'),
            value: (float) ($row->valor_movimento ?? 0),
            valueCurrency: 'BRL',
            tags: ['caixa'],
            timestamp: $timestamp,
            humanReadableTime: $timestamp->diffForHumans(),
            severity: 'success',
        );
    }

    private function makeCancelamentoDto(object $row): LogItemDTO
    {
        $timestamp = $this->buildTimestamp((int) $row->data_key, (string) $row->hora_evento);

        return new LogItemDTO(
            id: (int) $row->log_financeiro_id,
            type: 'cancelamento',
            actorName: (string) ($row->usuario_nome ?? 'Sistema'),
            actorAvatar: '',
            action: (string) ($row->descricao ?? 'Cancelamento'),
            relatedRecordName: 'Cancelamento',
            relatedRecordUrl: '',
            module: 'Cancelamentos',
            moduleIcon: 'x-circle',
            operationType: (string) ($row->tipo_descricao ?? 'CANCELAMENTO'),
            value: (float) ($row->valor_cancelamento ?? 0),
            valueCurrency: 'BRL',
            tags: ['cancelamento'],
            timestamp: $timestamp,
            humanReadableTime: $timestamp->diffForHumans(),
            severity: 'danger',
        );
    }

    private function buildTimestamp(int $dataKey, string $horaEvento): Carbon
    {
        $date = Carbon::createFromFormat('Ymd', (string) $dataKey);
        $hora = preg_match('/^\d{2}:\d{2}:\d{2}$/', $horaEvento) ? $horaEvento : '00:00:00';
        return Carbon::parse($date->format('Y-m-d') . ' ' . $hora);
    }
}
