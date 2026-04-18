<?php

namespace App\Services;

use App\Events\CaixaLogDetected;
use App\Events\CancelamentoLogDetected;
use App\Events\LogDetected;
use App\Events\SystemLogDetected;
use App\Models\CaixaLog;
use App\Models\CancelamentoLog;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * LogWatcherService
 * 
 * Monitora o Firebird production em busca de novos logs.
 * Usa cursor pagination para eficiência.
 * Emite eventos via Laravel Broadcasting para WebSocket.
 * 
 * Executado periodicamente via Schedule ou supervisor.
 */
class LogWatcherService
{
    private const CACHE_PREFIX = 'log_watcher_';
    private const BATCH_SIZE = 50;

    public function __construct(
        private LogEnricherService $enricher,
        private LogParserService $parser,
    ) {}

    /**
     * Monitora todos os logs e emite eventos para novos
     */
    public function watchAll(): array
    {
        $stats = [
            'system_logs' => 0,
            'caixa_logs' => 0,
            'cancelamento_logs' => 0,
            'total' => 0,
        ];

        $stats['system_logs'] = $this->watchSystemLogs();
        $stats['caixa_logs'] = $this->watchCaixaLogs();
        $stats['cancelamento_logs'] = $this->watchCancelamentoLogs();
        $stats['total'] = $stats['system_logs'] + $stats['caixa_logs'] + $stats['cancelamento_logs'];

        return $stats;
    }

    /**
     * Monitora USUARIOS_LOG (auditoria do sistema)
     */
    public function watchSystemLogs(): int
    {
        $lastId = $this->getLastId('system');
        $limit = self::BATCH_SIZE;

        // Raw query com cursor pagination
        $logs = DB::connection('firebird_prod')->select(
            'SELECT * FROM USUARIOS_LOG WHERE CODIGO > ? ORDER BY CODIGO ASC ROWS 1 TO ?',
            [$lastId, $limit]
        );

        $count = 0;
        foreach ($logs as $row) {
            try {
                $model = new SystemLog();
                
                // Popula atributos do row
                foreach ((array) $row as $key => $value) {
                    if (!in_array($key, [\PDO::ATTR_DRIVER_NAME])) {
                        $model->setAttribute($key, $value);
                    }
                }

                $dto = $this->enricher->enrichSystemLog($model);
                
                // Dispara eventos
                SystemLogDetected::dispatch($dto);
                LogDetected::dispatch($dto, 'logs.system');
                
                $this->setLastId('system', $row->CODIGO);
                $count++;
            } catch (\Exception $e) {
                \Log::error("Error processing SystemLog {$row->CODIGO}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Monitora LOG_CAIXA (transações financeiras)
     */
    public function watchCaixaLogs(): int
    {
        $lastId = $this->getLastId('caixa');
        $limit = self::BATCH_SIZE;

        $logs = DB::connection('firebird_prod')->select(
            'SELECT * FROM LOG_CAIXA WHERE CODIGO > ? ORDER BY CODIGO ASC ROWS 1 TO ?',
            [$lastId, $limit]
        );

        $count = 0;
        foreach ($logs as $row) {
            try {
                $model = new CaixaLog();
                
                foreach ((array) $row as $key => $value) {
                    if (!in_array($key, [\PDO::ATTR_DRIVER_NAME])) {
                        $model->setAttribute($key, $value);
                    }
                }

                $dto = $this->enricher->enrichCaixaLog($model);
                
                // Eventos específicos por tipo de operação
                $operationType = $this->parser->classifyCaixaOperationType($model->DESCRICAO ?? '');
                
                CaixaLogDetected::dispatch($dto);
                LogDetected::dispatch($dto, "logs.caixa.{$operationType}");
                
                $this->setLastId('caixa', $row->CODIGO);
                $count++;
            } catch (\Exception $e) {
                \Log::error("Error processing CaixaLog {$row->CODIGO}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Monitora LOG_CANCELAMENTO (cancelamentos críticos)
     */
    public function watchCancelamentoLogs(): int
    {
        $lastId = $this->getLastId('cancelamento');
        $limit = self::BATCH_SIZE;

        $logs = DB::connection('firebird_prod')->select(
            'SELECT * FROM LOG_CANCELAMENTO WHERE CODIGO > ? ORDER BY CODIGO ASC ROWS 1 TO ?',
            [$lastId, $limit]
        );

        $count = 0;
        foreach ($logs as $row) {
            try {
                $model = new CancelamentoLog();
                
                foreach ((array) $row as $key => $value) {
                    if (!in_array($key, [\PDO::ATTR_DRIVER_NAME])) {
                        $model->setAttribute($key, $value);
                    }
                }

                $dto = $this->enricher->enrichCancelamentoLog($model);
                
                // Cancelamentos são SEMPRE críticos
                CancelamentoLogDetected::dispatch($dto);
                LogDetected::dispatch($dto, 'logs.critico');
                
                $this->setLastId('cancelamento', $row->CODIGO);
                $count++;
            } catch (\Exception $e) {
                \Log::error("Error processing CancelamentoLog {$row->CODIGO}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Obtém último CODIGO visto para cada tabela
     */
    private function getLastId(string $type): int
    {
        $key = self::CACHE_PREFIX . $type;
        
        // Primeiro tenta Redis/Cache
        if ($cached = Cache::get($key)) {
            return (int) $cached;
        }

        // Se não tem cache, inicia do 0
        return 0;
    }

    /**
     * Salva último CODIGO visto
     */
    private function setLastId(string $type, int $id): void
    {
        $key = self::CACHE_PREFIX . $type;
        
        // Salva em cache por muito tempo (dias)
        Cache::put($key, $id, now()->addDays(30));
    }

    /**
     * Reseta o watcher (para testes)
     */
    public function reset(?string $type = null): void
    {
        if ($type) {
            Cache::forget(self::CACHE_PREFIX . $type);
        } else {
            Cache::forget(self::CACHE_PREFIX . 'system');
            Cache::forget(self::CACHE_PREFIX . 'caixa');
            Cache::forget(self::CACHE_PREFIX . 'cancelamento');
        }
    }

    /**
     * Obtém status atual do watcher
     */
    public function getStatus(): array
    {
        return [
            'system_last_id' => $this->getLastId('system'),
            'caixa_last_id' => $this->getLastId('caixa'),
            'cancelamento_last_id' => $this->getLastId('cancelamento'),
            'system_total' => DB::connection('firebird_prod')->selectOne('SELECT COUNT(*) as cnt FROM USUARIOS_LOG')->cnt,
            'caixa_total' => DB::connection('firebird_prod')->selectOne('SELECT COUNT(*) as cnt FROM LOG_CAIXA')->cnt,
            'cancelamento_total' => DB::connection('firebird_prod')->selectOne('SELECT COUNT(*) as cnt FROM LOG_CANCELAMENTO')->cnt,
        ];
    }
}
