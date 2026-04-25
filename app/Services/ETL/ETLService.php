<?php

namespace App\Services\ETL;

use App\Models\Dimensions\{
    DimUsuario, DimData, DimModulo, DimTipoOperacao,
    DimCliente, DimProfissional, DimUsuarioAlvo
};
use App\Models\Facts\{FatoLogsSistema, FatoLogsFinanceiro};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Serviço ETL - Extrai dados do Firebird e carrega em Star Schema SQLite
 * 
 * Estratégia:
 * 1. Full Load: Carrega todo histórico (170k+ logs)
 * 2. Incremental: Sincroniza apenas novos registros
 * 
 * Performance: Usa batch inserts e transações
 */
class ETLService
{
    protected $batchSize = 1000;
    protected $firebird;
    protected $lastSyncTimestamp = null;
    protected array $nomesUsuariosCache = [];
    protected array $vendasCache = [];

    public function __construct()
    {
        $this->firebird = DB::connection('firebird_prod');
    }

    /**
     * Carrega todo o histórico do Firebird → SQLite
     */
    public function fullLoad(?int $maxBatches = null, ?int $days = null): array
    {
        $cutoffDate = $days !== null ? Carbon::now()->subDays($days)->startOfDay() : null;

        $stats = [
            'dim_usuario' => 0,
            'dim_data' => 0,
            'dim_modulo' => 0,
            'dim_tipo_operacao' => 0,
            'dim_cliente' => 0,
            'dim_profissional' => 0,
            'dim_usuario_alvo' => 0,
            'fato_logs_sistema' => 0,
            'fato_logs_financeiro' => 0,
        ];

        // Evita lock em SQLite durante cargas longas.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA busy_timeout = 10000');
        }

        $this->limparTabelasWarehouse();

        // 1. Carrega dimensões
        echo "📊 Carregando dimensões...\n";
        $stats['dim_usuario'] = $this->carregarDimensaoUsuarios($cutoffDate);
        $stats['dim_data'] = $this->carregarDimensaoDatas($cutoffDate);
        $stats['dim_tipo_operacao'] = $this->carregarDimensaoTiposOperacao($cutoffDate);

        // 2. Carrega fatos de logs
        echo "📝 Carregando logs do sistema...\n";
        $stats['fato_logs_sistema'] = $this->carregarLogsSistema($maxBatches, $cutoffDate);

        echo "💳 Carregando logs financeiros...\n";
        $stats['fato_logs_financeiro'] = $this->carregarLogsFinanceiros($maxBatches, $cutoffDate);

        // Dimensões carregadas dinamicamente durante os fatos.
        $stats['dim_modulo'] = DimModulo::count();
        $stats['dim_cliente'] = DimCliente::count();
        $stats['dim_profissional'] = DimProfissional::count();
        $stats['dim_usuario_alvo'] = DimUsuarioAlvo::count();

        return $stats;
    }

    private function limparTabelasWarehouse(): void
    {
        $tabelas = [
            'fato_venda_itens',
            'fato_logs_sistema',
            'fato_logs_financeiro',
            'dim_usuario',
            'dim_data',
            'dim_modulo',
            'dim_tipo_operacao',
            'dim_cliente',
        ];

        foreach ($tabelas as $tabela) {
            $this->executarComRetry(function () use ($tabela) {
                DB::table($tabela)->delete();
            });
        }
    }

    private function executarComRetry(callable $callback, int $tentativas = 5): void
    {
        $ultimaExcecao = null;

        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $callback();
                return;
            } catch (\Throwable $e) {
                $ultimaExcecao = $e;
                if (!str_contains(strtolower($e->getMessage()), 'database is locked')) {
                    throw $e;
                }
                usleep(200000);
            }
        }

        throw $ultimaExcecao;
    }

    /**
     * Sincroniza apenas registros novos desde última execução
     */
    public function incrementalLoad(): array
    {
        $lastSyncRaw = DB::table('fato_logs_sistema')->max('data_sincronizacao');
        $lastSync = $lastSyncRaw
            ? Carbon::parse((string) $lastSyncRaw)
            : Carbon::now()->subDays(30);

        // Re-sincroniza a dimensão para atualizar nomes legados como "SEM INFORMAÇÃO".
        $this->carregarDimensaoUsuarios();

        $stats = [
            'logs_sistema' => 0,
            'logs_financeiro' => 0,
        ];

        DB::transaction(function () use ($lastSync, &$stats) {
            $stats['logs_sistema'] = $this->carregarLogsSistemaIncremental($lastSync);
            $stats['logs_financeiro'] = $this->carregarLogsFinanceirosIncremental($lastSync);
        });

        return $stats;
    }

    /**
     * Preenche detalhes de venda (cliente + itens) para logs financeiros já carregados.
     */
    public function backfillFinanceiroVendasDetalhes(?int $limit = null): array
    {
        $processados = 0;
        $atualizados = 0;
        $itensSincronizados = 0;
        $ultimoId = 0;
        $tamanhoLote = 500;
        $limiteAtingidoPrimeiraPassada = false;

        while (true) {
            $query = DB::table('fato_logs_financeiro')
                ->where('tipo_movimento', 'CAIXA')
                ->where('log_financeiro_id', '>', $ultimoId)
                ->where(function ($q) {
                    $q->whereNull('codigo_venda')
                        ->orWhere('codigo_venda', 0);
                })
                ->where('descricao', 'like', '%VENDA%')
                ->orderBy('log_financeiro_id')
                ->limit($tamanhoLote)
                ->get([
                    'log_financeiro_id',
                    'descricao',
                    'codigo_log_firebird',
                ]);

            if ($query->isEmpty()) {
                break;
            }

            $itensPorCodigoFirebird = [];

            foreach ($query as $row) {
                if ($limit !== null && $processados >= $limit) {
                    $limiteAtingidoPrimeiraPassada = true;
                    break;
                }

                $processados++;
                $ultimoId = (int) $row->log_financeiro_id;

                $descricao = $this->normalizarTextoFirebird($row->descricao ?? null);
                $codigoVenda = $this->extrairNumeroVendaDaDescricao($descricao);

                if ($codigoVenda === null) {
                    continue;
                }

                $contextoVenda = $this->obterContextoVenda($codigoVenda);

                $clienteId = null;
                if (($contextoVenda['codigo_cliente'] ?? 0) > 0) {
                    $cliente = DimCliente::buscarOuCriar(
                        (int) $contextoVenda['codigo_cliente'],
                        (string) ($contextoVenda['cliente_nome'] ?? 'SEM INFORMAÇÃO')
                    );

                    $clienteId = $cliente->cliente_id;
                }

                DB::table('fato_logs_financeiro')
                    ->where('log_financeiro_id', (int) $row->log_financeiro_id)
                    ->update([
                        'codigo_venda' => $codigoVenda,
                        'cliente_id' => $clienteId,
                        'updated_at' => now(),
                    ]);

                $atualizados++;

                $codigoFirebird = (int) ($row->codigo_log_firebird ?? 0);
                if ($codigoFirebird > 0) {
                    $itensPorCodigoFirebird[$codigoFirebird] = [
                        'codigo_venda' => $codigoVenda,
                        'itens' => $contextoVenda['itens'] ?? [],
                    ];
                }
            }

            if (!empty($itensPorCodigoFirebird)) {
                $this->sincronizarItensVendaPorCodigoFirebird($itensPorCodigoFirebird);
                $itensSincronizados += count($itensPorCodigoFirebird);
            }

            if ($limiteAtingidoPrimeiraPassada) {
                break;
            }
        }

        // Segunda passada: garante itens para logs já enriquecidos (codigo_venda preenchido) sem registros em fato_venda_itens.
        $ultimoIdSemItens = 0;
        while (true) {
            $querySemItens = DB::table('fato_logs_financeiro as f')
                ->leftJoin('fato_venda_itens as i', 'i.log_financeiro_id', '=', 'f.log_financeiro_id')
                ->where('f.tipo_movimento', 'CAIXA')
                ->where('f.log_financeiro_id', '>', $ultimoIdSemItens)
                ->whereNotNull('f.codigo_venda')
                ->where('f.codigo_venda', '>', 0)
                ->whereNotNull('f.codigo_log_firebird')
                ->where('f.codigo_log_firebird', '>', 0)
                ->whereNull('i.log_financeiro_id')
                ->orderBy('f.log_financeiro_id')
                ->limit($tamanhoLote)
                ->get([
                    'f.log_financeiro_id',
                    'f.codigo_venda',
                    'f.codigo_log_firebird',
                ]);

            if ($querySemItens->isEmpty()) {
                break;
            }

            $itensPorCodigoFirebird = [];
            foreach ($querySemItens as $row) {
                $processados++;
                $ultimoIdSemItens = (int) $row->log_financeiro_id;

                $codigoVenda = (int) ($row->codigo_venda ?? 0);
                $codigoFirebird = (int) ($row->codigo_log_firebird ?? 0);

                if ($codigoVenda <= 0 || $codigoFirebird <= 0) {
                    continue;
                }

                $contextoVenda = $this->obterContextoVenda($codigoVenda);
                $itensPorCodigoFirebird[$codigoFirebird] = [
                    'codigo_venda' => $codigoVenda,
                    'itens' => $contextoVenda['itens'] ?? [],
                ];
            }

            if (!empty($itensPorCodigoFirebird)) {
                $this->sincronizarItensVendaPorCodigoFirebird($itensPorCodigoFirebird);
                $itensSincronizados += count($itensPorCodigoFirebird);
            }
        }

        return [
            'processados' => $processados,
            'atualizados' => $atualizados,
            'itens_sincronizados' => $itensSincronizados,
        ];
    }

    /**
     * Carrega dimensão de usuários
     */
    private function carregarDimensaoUsuarios(?Carbon $cutoffDate = null): int
    {
        if ($cutoffDate) {
            $usuarios = $this->firebird->select(
                'SELECT DISTINCT L.CODIGO_USUARIO, U.NOME
                 FROM USUARIOS_LOG L
                 LEFT JOIN USUARIOS U ON U.CODIGO = L.CODIGO_USUARIO
                 WHERE L.DATA >= CAST(? AS DATE)
                 ORDER BY L.CODIGO_USUARIO',
                [$cutoffDate->toDateString()]
            );
        } else {
            $usuarios = $this->firebird->select(
                'SELECT DISTINCT L.CODIGO_USUARIO, U.NOME
                 FROM USUARIOS_LOG L
                 LEFT JOIN USUARIOS U ON U.CODIGO = L.CODIGO_USUARIO
                 ORDER BY L.CODIGO_USUARIO'
            );
        }

        $batch = [];
        foreach ($usuarios as $row) {
            if (is_object($row) || is_array($row)) {
                $codigo = (int)($row->CODIGO_USUARIO ?? 0);
                if ($codigo > 0) {
                    $nome = $this->normalizarTextoFirebird($row->NOME ?? null);
                    $this->nomesUsuariosCache[$codigo] = $nome;

                    $batch[] = [
                        'codigo_usuario' => $codigo,
                        'nome' => $nome,
                        'ativo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($batch, $this->batchSize) as $chunk) {
            DB::table('dim_usuario')->upsert(
                $chunk,
                ['codigo_usuario'],
                ['nome', 'ativo', 'updated_at']
            );
        }

        return DimUsuario::count();
    }

    /**
     * Carrega dimensão de datas (últimos 5 anos)
     */
    private function carregarDimensaoDatas(?Carbon $cutoffDate = null): int
    {
        $dataInicio = $cutoffDate
            ? $cutoffDate->copy()
            : Carbon::now()->subYears(5);
        $dataFim = Carbon::now();
        $batch = [];

        while ($dataInicio <= $dataFim) {
            $chave = DimData::gerarChave($dataInicio);
            $batch[] = [
                'data_key' => $chave,
                'data_completa' => $dataInicio->toDateString(),
                'dia_mes' => $dataInicio->day,
                'mes' => $dataInicio->month,
                'ano' => $dataInicio->year,
                'trimestre' => ceil($dataInicio->month / 3),
                'dia_semana' => $dataInicio->format('l'),
                'mes_nome' => $dataInicio->getTranslatedMonthName('pt_BR'),
                'eh_fim_semana' => $dataInicio->isWeekend(),
            ];

            $dataInicio->addDay();

            if (count($batch) >= $this->batchSize) {
                DB::table('dim_data')->insertOrIgnore($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('dim_data')->insertOrIgnore($batch);
        }

        return DimData::count();
    }

    /**
     * Carrega tipos de operação únicos dos logs
     */
    private function carregarDimensaoTiposOperacao(?Carbon $cutoffDate = null): int
    {
        if ($cutoffDate) {
            $tipos = $this->firebird->select(
                'SELECT DISTINCT TIPO_OPERACAO
                 FROM USUARIOS_LOG
                 WHERE TIPO_OPERACAO IS NOT NULL
                   AND DATA >= CAST(? AS DATE)',
                [$cutoffDate->toDateString()]
            );
        } else {
            $tipos = $this->firebird->select(
                'SELECT DISTINCT TIPO_OPERACAO FROM USUARIOS_LOG WHERE TIPO_OPERACAO IS NOT NULL'
            );
        }

        $batch = [];
        foreach ($tipos as $row) {
            if (isset($row->TIPO_OPERACAO)) {
                $batch[] = [
                    'tipo_descricao' => substr((string)$row->TIPO_OPERACAO, 0, 100),
                    'categoria' => $this->classificarOperacao((string)$row->TIPO_OPERACAO),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($batch, $this->batchSize) as $chunk) {
            DB::table('dim_tipo_operacao')->insertOrIgnore($chunk);
        }

        return DimTipoOperacao::count();
    }

    /**
     * Carrega logs do sistema com paginação - OTIMIZADO COM CACHE DE DIMENSÕES
     * Estratégia simples: Pré-carrega tudo, usa lookups em memória
     */
    private function carregarLogsSistema(?int $maxBatches = null, ?Carbon $cutoffDate = null): int
    {
        $totalLinhasProcessadas = 0;
        $offset = 0;
        $limit = $this->batchSize;
        $maxIteracoes = $maxBatches ?? 200;
        $iteracao = 0;

        // Pré-carrega todas as dimensões UMA VEZ (4 queries apenas)
        $usuarios = DimUsuario::all()->mapWithKeys(fn($u) => [$u->codigo_usuario => $u]);
        $tipos = DimTipoOperacao::all()->mapWithKeys(fn($t) => [$t->tipo_descricao => $t]);
        $modulos = DimModulo::all()->mapWithKeys(fn($m) => [$m->nome => $m]);
        $datas = DimData::all()->mapWithKeys(fn($d) => [$d->data_key => $d]);

        while ($iteracao < $maxIteracoes) {
            try {
                if ($cutoffDate) {
                    $sql = "SELECT FIRST $limit SKIP $offset * FROM USUARIOS_LOG WHERE DATA >= CAST(? AS DATE) ORDER BY CODIGO DESC";
                    $logs = $this->firebird->select($sql, [$cutoffDate->toDateString()]);
                } else {
                    $sql = "SELECT FIRST $limit SKIP $offset * FROM USUARIOS_LOG ORDER BY CODIGO DESC";
                    $logs = $this->firebird->select($sql);
                }

                if (empty($logs)) {
                    echo "\n✅ Fim dos registros";
                    break;
                }

                $batch = [];

                foreach ($logs as $log) {
                    try {
                        $codigo_usuario = (int)($log->CODIGO_USUARIO ?? 0);
                        if ($codigo_usuario <= 0) continue;

                        // Lookups de memória (super rápido!)
                        $usuario = $usuarios->get($codigo_usuario);
                        if (!$usuario) {
                            // Se não existe, cria na hora
                            $nomeUsuario = $this->buscarNomeUsuarioFirebird($codigo_usuario);
                            $usuario = DimUsuario::firstOrCreate(
                                ['codigo_usuario' => $codigo_usuario],
                                ['nome' => $nomeUsuario, 'ativo' => true]
                            );
                            $usuarios->put($codigo_usuario, $usuario);
                        }

                        $tipoDescricao = trim(substr((string)($log->TIPO_OPERACAO ?? 'OUTRA'), 0, 100));
                        $tipoOp = $tipos->get($tipoDescricao);
                        if (!$tipoOp) {
                            $tipoOp = DimTipoOperacao::firstOrCreate(
                                ['tipo_descricao' => $tipoDescricao],
                                ['categoria' => $this->classificarOperacao($tipoDescricao)]
                            );
                            $tipos->put($tipoDescricao, $tipoOp);
                        }

                        $data = $this->parsearDataHora($log);
                        if (!$data) continue;

                        $dataKey = $data->format('Ymd');
                        $dimData = $datas->get($dataKey);
                        if (!$dimData) {
                            $dimData = DimData::buscarOuCriar($data);
                            $datas->put($dataKey, $dimData);
                        }

                        $modulo_nome = 'SEM MÓDULO';
                        if (isset($log->MODULO)) {
                            $modulo_raw = $log->MODULO;
                            if (is_resource($modulo_raw)) {
                                $modulo_nome = stream_get_contents($modulo_raw);
                            } else {
                                $modulo_nome = (string)$modulo_raw;
                            }
                        }
                        $modulo_nome = trim(substr($modulo_nome, 0, 100));
                        $modulo = $modulos->get($modulo_nome);
                        if (!$modulo) {
                            $modulo = DimModulo::firstOrCreate(
                                ['nome' => $modulo_nome],
                                ['descricao' => $modulo_nome]
                            );
                            $modulos->put($modulo_nome, $modulo);
                        }

                        if ($usuario && $tipoOp && $dimData && $modulo) {
                            $batch[] = [
                                'usuario_id' => $usuario->usuario_id,
                                'modulo_id' => $modulo->modulo_id,
                                'tipo_operacao_id' => $tipoOp->tipo_operacao_id,
                                'data_key' => $dimData->data_key,
                                'hora_evento' => $data->format('H:i:s'),
                                'descricao' => trim(substr((string)($log->DESCRICAO ?? ''), 0, 500)),
                                'codigo_movimentado' => (int)($log->CODIGO_MOVIMENTADO ?? 0),
                                'nome_movimentado' => trim(substr((string)($log->NOME_MOVIMENTADO ?? ''), 0, 255)),
                                'valor_movimentado' => (float)($log->VALOR_MOVIMENTADO ?? 0),
                                'quantidade' => 1,
                                'codigo_log_firebird' => (int)$log->CODIGO,
                                'data_sincronizacao' => Carbon::now(),
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ];
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if (!empty($batch)) {
                    DB::table('fato_logs_sistema')->insertOrIgnore($batch);
                    $totalLinhasProcessadas += count($batch);
                }

                $lidosNoBatch = count($logs);
                $inseridosNoBatch = count($batch);
                echo "📊 batch " . ($iteracao + 1) . " | lidos: {$lidosNoBatch} | inseridos: {$inseridosNoBatch} | total: {$totalLinhasProcessadas}\n";

                $offset += $limit;
                $iteracao++;

            } catch (\Exception $e) {
                echo "\n⚠️  Erro: {$e->getMessage()}\n";
                break;
            }
        }

        echo "\n";
        return $totalLinhasProcessadas;
    }

    /**
     * Carrega logs financeiros com cache de dimensões
     * OTIMIZAÇÃO: Pré-carrega dimensões em memória
     */
    private function carregarLogsFinanceiros(?int $maxBatches = null, ?Carbon $cutoffDate = null): int
    {
        $totalLinhasProcessadas = 0;
        $offset = 0;
        $limit = $this->batchSize;
        $maxIteracoes = $maxBatches ?? 200;
        $iteracao = 0;

        // PRÉ-CARREGA dimensões
        $usuariosMap = DimUsuario::all()->keyBy('codigo_usuario');
        $tiposOpMap = DimTipoOperacao::all()->keyBy('tipo_descricao');
        $datasMap = DimData::all()->keyBy('data_key');
        $clientesMap = DimCliente::all()->keyBy('codigo_cliente');

        // LOG_CAIXA
        while ($iteracao < $maxIteracoes) {
            if ($cutoffDate) {
                $logs = $this->firebird->select(
                    "SELECT FIRST $limit SKIP $offset * FROM LOG_CAIXA WHERE DATA_HORA >= CAST(? AS TIMESTAMP) ORDER BY DATA_HORA DESC",
                    [$cutoffDate->format('Y-m-d H:i:s')]
                );
            } else {
                $logs = $this->firebird->select(
                    "SELECT FIRST $limit SKIP $offset * FROM LOG_CAIXA ORDER BY DATA_HORA DESC"
                );
            }

            if (empty($logs)) {
                break;
            }

            $batch = [];
            $itensPorCodigoFirebird = [];
            foreach ($logs as $index => $log) {
                try {
                    $codigoUsuario = (int)($log->COD_USUARIO ?? 0);
                    $usuario = $usuariosMap[$codigoUsuario] ?? null;
                    if (!$usuario) continue;

                    $tipoDescricao = 'CAIXA';
                    $tipoOp = $tiposOpMap[$tipoDescricao] ?? null;
                    if (!$tipoOp) {
                        $tipoOp = DimTipoOperacao::buscarOuCriar($tipoDescricao, 'FINANCEIRO');
                        $tiposOpMap[$tipoDescricao] = $tipoOp;
                    }

                    $data = $this->parsearDataHora($log);
                    if (!$data) continue;

                    $dataKey = $data->format('Ymd');
                    $dimData = $datasMap[$dataKey] ?? null;
                    if (!$dimData) continue;

                    $descricao = $this->normalizarTextoFirebird($log->DESCRICAO ?? null);
                    $codigoVenda = $this->extrairNumeroVendaDaDescricao($descricao);
                    $clienteId = null;

                    if ($codigoVenda !== null) {
                        $contextoVenda = $this->obterContextoVenda($codigoVenda);

                        if (($contextoVenda['codigo_cliente'] ?? 0) > 0) {
                            $cliente = DimCliente::buscarOuCriar(
                                (int) $contextoVenda['codigo_cliente'],
                                (string) ($contextoVenda['cliente_nome'] ?? 'SEM INFORMAÇÃO')
                            );

                            $clienteId = $cliente->cliente_id;
                            $clientesMap[$cliente->codigo_cliente] = $cliente;
                        }
                    }

                    $codigoLogFirebird = abs(crc32('CAIXA|' . ($log->DATA_HORA ?? '') . '|' . ($log->COD_CAIXA ?? 0) . '|' . $offset . '|' . $index));

                    $registro = [
                        'usuario_id' => $usuario->usuario_id,
                        'cliente_id' => $clienteId,
                        'codigo_venda' => $codigoVenda,
                        'tipo_operacao_id' => $tipoOp->tipo_operacao_id,
                        'data_key' => $dimData->data_key,
                        'hora_evento' => $data->format('H:i:s'),
                        'tipo_movimento' => 'CAIXA',
                        'valor_movimento' => (float)($log->VALOR_MOVIMENTADO ?? 0),
                        'valor_cancelamento' => 0,
                        'descricao' => substr($descricao, 0, 500),
                        'codigo_log_firebird' => $codigoLogFirebird,
                        'data_sincronizacao' => Carbon::now(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];

                    if ($codigoVenda !== null) {
                        $contextoVenda = $this->obterContextoVenda($codigoVenda);
                        $itensPorCodigoFirebird[$codigoLogFirebird] = [
                            'codigo_venda' => $codigoVenda,
                            'itens' => $contextoVenda['itens'] ?? [],
                        ];
                    }

                    $batch[] = $registro;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!empty($batch)) {
                DB::table('fato_logs_financeiro')->insertOrIgnore($batch);
                $totalLinhasProcessadas += count($batch);

                if (!empty($itensPorCodigoFirebird)) {
                    $this->sincronizarItensVendaPorCodigoFirebird($itensPorCodigoFirebird);
                }
            }

            echo "💳 caixa batch " . ($iteracao + 1) . " | lidos: " . count($logs) . " | inseridos: " . count($batch) . " | total financeiro: {$totalLinhasProcessadas}\n";

            $offset += $limit;
            $iteracao++;
        }

        // LOG_CANCELAMENTO
        $offset = 0;
        $iteracao = 0;
        while ($iteracao < $maxIteracoes) {
            if ($cutoffDate) {
                $logs = $this->firebird->select(
                    "SELECT FIRST $limit SKIP $offset * FROM LOG_CANCELAMENTO WHERE DATA_HORA >= CAST(? AS TIMESTAMP) ORDER BY DATA_HORA DESC",
                    [$cutoffDate->format('Y-m-d H:i:s')]
                );
            } else {
                $logs = $this->firebird->select(
                    "SELECT FIRST $limit SKIP $offset * FROM LOG_CANCELAMENTO ORDER BY DATA_HORA DESC"
                );
            }

            if (empty($logs)) {
                break;
            }

            $batch = [];
            foreach ($logs as $index => $log) {
                try {
                    $codigoUsuario = (int)($log->COD_USUARIO ?? 0);
                    $usuario = $usuariosMap[$codigoUsuario] ?? null;
                    if (!$usuario) continue;

                    $tipoDescricao = (string)($log->TIPO_CANCELAMENTO ?? 'CANCELAMENTO');
                    $tipoOp = $tiposOpMap[$tipoDescricao] ?? null;
                    if (!$tipoOp) {
                        $tipoOp = DimTipoOperacao::buscarOuCriar($tipoDescricao, 'FINANCEIRO');
                        $tiposOpMap[$tipoDescricao] = $tipoOp;
                    }

                    $data = $this->parsearDataHora($log);
                    if (!$data) continue;

                    $dataKey = $data->format('Ymd');
                    $dimData = $datasMap[$dataKey] ?? null;
                    if (!$dimData) continue;

                    $batch[] = [
                        'usuario_id' => $usuario->usuario_id,
                        'tipo_operacao_id' => $tipoOp->tipo_operacao_id,
                        'data_key' => $dimData->data_key,
                        'hora_evento' => $data->format('H:i:s'),
                        'tipo_movimento' => 'CANCELAMENTO',
                        'valor_movimento' => 0,
                        'valor_cancelamento' => (float)($log->VALOR ?? 0),
                        'descricao' => substr((string)($log->DESCRICAO ?? ''), 0, 500),
                        'codigo_log_firebird' => abs(crc32('CANCEL|' . ($log->DATA_HORA ?? '') . '|' . ($log->COD_CLIENTE ?? 0) . '|' . ($log->NUM_VENDA ?? 0) . '|' . $offset . '|' . $index)),
                        'data_sincronizacao' => Carbon::now(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!empty($batch)) {
                DB::table('fato_logs_financeiro')->insertOrIgnore($batch);
                $totalLinhasProcessadas += count($batch);
            }

            echo "💳 cancelamento batch " . ($iteracao + 1) . " | lidos: " . count($logs) . " | inseridos: " . count($batch) . " | total financeiro: {$totalLinhasProcessadas}\n";

            $offset += $limit;
            $iteracao++;
        }

        return $totalLinhasProcessadas;
    }

    /**
     * Sincronização incremental de logs do sistema
     */
    private function carregarLogsSistemaIncremental(Carbon $lastSync): int
    {
        $sincronizados = 0;
        $lastSyncStr = $lastSync->format('Y-m-d H:i:s');

        $logs = $this->firebird->select(
            "SELECT * FROM USUARIOS_LOG WHERE DATA >= CAST(? AS DATE) ORDER BY CODIGO DESC",
            [$lastSync->toDateString()]
        );

        foreach (array_chunk($logs, $this->batchSize) as $batch) {
            $inserts = [];
            foreach ($batch as $log) {
                $codigoUsuario = (int)($log->CODIGO_USUARIO ?? 0);
                $usuario = DimUsuario::buscarOuCriar(
                    $codigoUsuario,
                    $this->buscarNomeUsuarioFirebird($codigoUsuario)
                );
                $modulo = DimModulo::buscarOuCriar((string)($log->MODULO ?? 'SEM MÓDULO'));
                $tipoOp = DimTipoOperacao::buscarOuCriar((string)($log->TIPO_OPERACAO ?? 'OUTRA'));

                $data = $this->parsearDataHora($log);
                if (!$data) {
                    continue;
                }
                $dimData = DimData::buscarOuCriar($data);

                if ($usuario && $modulo && $tipoOp && $dimData) {
                    $inserts[] = [
                        'usuario_id' => $usuario->usuario_id,
                        'modulo_id' => $modulo->modulo_id,
                        'tipo_operacao_id' => $tipoOp->tipo_operacao_id,
                        'data_key' => $dimData->data_key,
                        'hora_evento' => $data->format('H:i:s'),
                        'descricao' => substr((string)($log->DESCRICAO ?? ''), 0, 500),
                        'codigo_movimentado' => $log->CODIGO_MOVIMENTADO ?? 0,
                        'nome_movimentado' => substr((string)($log->NOME_MOVIMENTADO ?? ''), 0, 255),
                        'valor_movimentado' => (float)($log->VALOR_MOVIMENTADO ?? 0),
                        'quantidade' => 1,
                        'codigo_log_firebird' => (int)$log->CODIGO,
                        'data_sincronizacao' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($inserts)) {
                DB::table('fato_logs_sistema')->insertOrIgnore($inserts);
                $sincronizados += count($inserts);
            }
        }

        return $sincronizados;
    }

    /**
     * Sincronização incremental de logs financeiros
     */
    private function carregarLogsFinanceirosIncremental(Carbon $lastSync): int
    {
        $sincronizados = 0;

        $logs = $this->firebird->select(
            "SELECT * FROM LOG_CAIXA WHERE DATA_HORA >= CAST(? AS TIMESTAMP) ORDER BY DATA_HORA DESC",
            [$lastSync->format('Y-m-d H:i:s')]
        );

        foreach (array_chunk($logs, $this->batchSize) as $batch) {
            $inserts = [];
            $itensPorCodigoFirebird = [];
            foreach ($batch as $log) {
                $codigoUsuario = (int)($log->COD_USUARIO ?? 0);
                $usuario = DimUsuario::buscarOuCriar(
                    $codigoUsuario,
                    $this->buscarNomeUsuarioFirebird($codigoUsuario)
                );
                $tipoOp = DimTipoOperacao::buscarOuCriar('CAIXA');
                $data = $this->parsearDataHora($log);
                if (!$data) {
                    continue;
                }
                $dimData = DimData::buscarOuCriar($data);

                $descricao = $this->normalizarTextoFirebird($log->DESCRICAO ?? null);
                $codigoVenda = $this->extrairNumeroVendaDaDescricao($descricao);
                $clienteId = null;

                if ($codigoVenda !== null) {
                    $contextoVenda = $this->obterContextoVenda($codigoVenda);

                    if (($contextoVenda['codigo_cliente'] ?? 0) > 0) {
                        $cliente = DimCliente::buscarOuCriar(
                            (int) $contextoVenda['codigo_cliente'],
                            (string) ($contextoVenda['cliente_nome'] ?? 'SEM INFORMAÇÃO')
                        );

                        $clienteId = $cliente->cliente_id;
                    }
                }

                if ($usuario && $tipoOp && $dimData) {
                    $codigoLogFirebird = abs(crc32('CAIXA|' . ($log->DATA_HORA ?? '') . '|' . ($log->COD_CAIXA ?? 0)));

                    $registro = [
                        'usuario_id' => $usuario->usuario_id,
                        'cliente_id' => $clienteId,
                        'codigo_venda' => $codigoVenda,
                        'tipo_operacao_id' => $tipoOp->tipo_operacao_id,
                        'data_key' => $dimData->data_key,
                        'hora_evento' => $data->format('H:i:s'),
                        'tipo_movimento' => 'CAIXA',
                        'valor_movimento' => (float)($log->VALOR_MOVIMENTADO ?? 0),
                        'valor_cancelamento' => 0,
                        'descricao' => substr($descricao, 0, 500),
                        'codigo_log_firebird' => $codigoLogFirebird,
                        'data_sincronizacao' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($codigoVenda !== null) {
                        $contextoVenda = $this->obterContextoVenda($codigoVenda);
                        $itensPorCodigoFirebird[$codigoLogFirebird] = [
                            'codigo_venda' => $codigoVenda,
                            'itens' => $contextoVenda['itens'] ?? [],
                        ];
                    }

                    $inserts[] = [
                        ...$registro,
                    ];
                }
            }

            if (!empty($inserts)) {
                DB::table('fato_logs_financeiro')->insertOrIgnore($inserts);
                $sincronizados += count($inserts);

                if (!empty($itensPorCodigoFirebird)) {
                    $this->sincronizarItensVendaPorCodigoFirebird($itensPorCodigoFirebird);
                }
            }
        }

        return $sincronizados;
    }

    /**
     * Classifica tipo de operação em categoria
     */
    private function classificarOperacao(string $tipo): string
    {
        $tipo = strtoupper($tipo);

        if (str_contains($tipo, 'ALTERAR') || str_contains($tipo, 'EDITAR') || str_contains($tipo, 'MODIFICAR')) {
            return 'ALTERAÇÃO';
        } elseif (str_contains($tipo, 'CRIAR') || str_contains($tipo, 'INSERIR') || str_contains($tipo, 'NOVA')) {
            return 'CRIAÇÃO';
        } elseif (str_contains($tipo, 'DELETAR') || str_contains($tipo, 'REMOVER') || str_contains($tipo, 'EXCLUIR')) {
            return 'EXCLUSÃO';
        } elseif (str_contains($tipo, 'CONSULTA') || str_contains($tipo, 'VISUALIZAR') || str_contains($tipo, 'VER')) {
            return 'LEITURA';
        } elseif (str_contains($tipo, 'CAIXA') || str_contains($tipo, 'VENDA') || str_contains($tipo, 'MOVIMENTO')) {
            return 'FINANCEIRO';
        }

        return 'OUTRA';
    }

    /**
     * Parse data/hora dos campos Firebird
     */
    private function parsearDataHora($log): ?Carbon
    {
        try {
            if (isset($log->DATA_HORA) && !empty((string)$log->DATA_HORA)) {
                return Carbon::parse((string)$log->DATA_HORA);
            }

            // Tenta DATA + HORA
            if (isset($log->DATA) && isset($log->HORA)) {
                $dataCarbon = Carbon::parse((string)$log->DATA);
                $horaTexto = trim((string)$log->HORA);

                if (preg_match('/(\d{2}:\d{2}:\d{2})/', $horaTexto, $matchHora)) {
                    [$h, $m, $s] = explode(':', $matchHora[1]);
                    return $dataCarbon->copy()->setTime((int)$h, (int)$m, (int)$s);
                }

                return $dataCarbon;
            }
        } catch (\Exception $e) {
            // Continua com null
        }

        return null;
    }

    private function buscarNomeUsuarioFirebird(int $codigoUsuario): string
    {
        if ($codigoUsuario <= 0) {
            return 'SEM INFORMAÇÃO';
        }

        if (isset($this->nomesUsuariosCache[$codigoUsuario])) {
            return $this->nomesUsuariosCache[$codigoUsuario];
        }

        $resultado = $this->firebird->selectOne(
            'SELECT FIRST 1 NOME FROM USUARIOS WHERE CODIGO = ?',
            [$codigoUsuario]
        );

        $nome = $this->normalizarTextoFirebird($resultado->NOME ?? null);
        $this->nomesUsuariosCache[$codigoUsuario] = $nome;

        return $nome;
    }

    private function normalizarTextoFirebird(mixed $valor): string
    {
        if (is_resource($valor)) {
            $valor = stream_get_contents($valor);
        }

        $texto = trim((string) ($valor ?? ''));

        if ($texto === '') {
            return 'SEM INFORMAÇÃO';
        }

        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        }

        return mb_substr($texto, 0, 255);
    }

    private function extrairNumeroVendaDaDescricao(?string $descricao): ?int
    {
        if (!$descricao) {
            return null;
        }

        if (preg_match('/VENDA\s*N[ºO:]?\s*:?\s*(\d+)/iu', $descricao, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/VENDA\s*(\d+)/iu', $descricao, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function obterContextoVenda(int $codigoVenda): array
    {
        if ($codigoVenda <= 0) {
            return [
                'codigo_cliente' => null,
                'cliente_nome' => null,
                'itens' => [],
            ];
        }

        if (isset($this->vendasCache[$codigoVenda])) {
            return $this->vendasCache[$codigoVenda];
        }

        $venda = $this->firebird->selectOne(
            'SELECT FIRST 1 V.CODIGO_CLIENTE, C.NOME
             FROM VENDAS V
             LEFT JOIN CLIENTES C ON C.CODIGO = V.CODIGO_CLIENTE
             WHERE V.CODIGO = ?',
            [$codigoVenda]
        );

        $itens = $this->firebird->select(
            'SELECT
                VI.CODIGO_ITEM,
                VI.TIPO_PEDIDO,
                VI.CODIGO_PEDIDO,
                VI.NOME_PEDIDO,
                VI.QUANTIDADE,
                VI.PRECO_TOTAL,
                P.NOME AS PRODUTO_NOME,
                S.NOME AS SERVICO_NOME
             FROM VENDAS_ITENS VI
             LEFT JOIN PRODUTOS P ON P.CODIGO = VI.CODIGO_PEDIDO
             LEFT JOIN SERVICOS S ON S.CODIGO = VI.CODIGO_PEDIDO
             WHERE VI.CODIGO = ?
             ORDER BY VI.CODIGO_ITEM',
            [$codigoVenda]
        );

        $contexto = [
            'codigo_cliente' => (int) ($venda->CODIGO_CLIENTE ?? 0),
            'cliente_nome' => $this->normalizarTextoFirebird($venda->NOME ?? null),
            'itens' => array_map(function ($item) {
                $tipo = $this->mapearTipoPedidoParaItem((string) ($item->TIPO_PEDIDO ?? ''));

                $nome = $item->NOME_PEDIDO
                    ?? $item->PRODUTO_NOME
                    ?? $item->SERVICO_NOME
                    ?? 'Item';

                return [
                    'codigo_item' => isset($item->CODIGO_ITEM) ? (int) $item->CODIGO_ITEM : null,
                    'codigo_item_catalogo' => isset($item->CODIGO_PEDIDO) ? (int) $item->CODIGO_PEDIDO : null,
                    'tipo_item' => $tipo,
                    'nome_item' => $this->normalizarTextoFirebird($nome),
                    'quantidade' => (float) ($item->QUANTIDADE ?? 1),
                    'valor_total' => (float) ($item->PRECO_TOTAL ?? 0),
                ];
            }, $itens),
        ];

        $this->vendasCache[$codigoVenda] = $contexto;

        return $contexto;
    }

    private function mapearTipoPedidoParaItem(string $tipoPedido): string
    {
        $tipo = strtoupper(trim($tipoPedido));

        if (str_contains($tipo, 'SERV')) {
            return 'SERVICO';
        }

        if (str_contains($tipo, 'PROD')) {
            return 'PRODUTO';
        }

        return 'OUTRO';
    }

    private function sincronizarItensVendaPorCodigoFirebird(array $itensPorCodigoFirebird): void
    {
        if (empty($itensPorCodigoFirebird)) {
            return;
        }

        $mapaLogs = DB::table('fato_logs_financeiro')
            ->whereIn('codigo_log_firebird', array_keys($itensPorCodigoFirebird))
            ->pluck('log_financeiro_id', 'codigo_log_firebird')
            ->toArray();

        $mapaLogsNormalizado = [];
        foreach ($mapaLogs as $codigo => $logId) {
            $mapaLogsNormalizado[(int) $codigo] = (int) $logId;
        }

        if (empty($mapaLogsNormalizado)) {
            return;
        }

        $logIds = array_values($mapaLogsNormalizado);
        DB::table('fato_venda_itens')->whereIn('log_financeiro_id', $logIds)->delete();

        $insertItens = [];
        foreach ($itensPorCodigoFirebird as $codigoLogFirebird => $payload) {
            $logId = $mapaLogsNormalizado[(int) $codigoLogFirebird] ?? null;
            if (!$logId) {
                continue;
            }

            foreach ($payload['itens'] as $item) {
                $insertItens[] = [
                    'log_financeiro_id' => (int) $logId,
                    'codigo_venda' => (int) ($payload['codigo_venda'] ?? 0),
                    'codigo_item' => $item['codigo_item'],
                    'codigo_item_catalogo' => $item['codigo_item_catalogo'],
                    'tipo_item' => $item['tipo_item'],
                    'nome_item' => mb_substr((string) $item['nome_item'], 0, 255),
                    'quantidade' => (float) ($item['quantidade'] ?? 1),
                    'valor_total' => (float) ($item['valor_total'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($insertItens, $this->batchSize) as $chunk) {
            DB::table('fato_venda_itens')->insert($chunk);
        }
    }
}
