<?php

namespace App\Console\Commands;

use App\Services\LogWatcherService;
use Illuminate\Console\Command;

/**
 * Command: logs:watch
 * 
 * Monitora Firebird em busca de novos logs e emite eventos via WebSocket.
 * Deve rodar continuamente via Supervisor ou Scheduler.
 * 
 * Uso:
 *   php artisan logs:watch          # Roda uma vez
 *   php artisan logs:watch --loop   # Roda em loop (5s interval)
 *   php artisan logs:watch --status # Mostra status do watcher
 */
class LogsWatchCommand extends Command
{
    protected $signature = 'logs:watch 
        {--loop : Roda em loop contínuo}
        {--interval=5 : Intervalo em segundos para loop}
        {--status : Mostra status do watcher}
        {--reset : Reseta o watcher para começar do 0}
        {--reset-type= : Reseta um tipo específico (system, caixa, cancelamento)}';

    protected $description = 'Monitora logs do Firebird e transmite alterações via WebSocket';

    public function __construct(
        private LogWatcherService $watcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Flag --status
        if ($this->option('status')) {
            return $this->showStatus();
        }

        // Flag --reset
        if ($this->option('reset')) {
            $this->watcher->reset();
            $this->info('Watcher resetado!');
            return 0;
        }

        // Flag --reset-type
        if ($resetType = $this->option('reset-type')) {
            $this->watcher->reset($resetType);
            $this->info("Watcher resetado para: {$resetType}");
            return 0;
        }

        // Loop mode
        if ($this->option('loop')) {
            return $this->watchLoop();
        }

        // Run once
        return $this->watchOnce();
    }

    private function watchOnce(): int
    {
        $this->info('🔍 Verificando novos logs no Firebird...');

        try {
            $stats = $this->watcher->watchAll();

            if ($stats['total'] > 0) {
                $this->info("✅ {$stats['total']} novos logs detectados:");
                $this->table(
                    ['Tipo', 'Quantidade'],
                    [
                        ['System', $stats['system_logs']],
                        ['Caixa', $stats['caixa_logs']],
                        ['Cancelamento', $stats['cancelamento_logs']],
                    ]
                );
            } else {
                $this->info('ℹ️ Nenhum novo log detectado.');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Erro ao monitorar logs: {$e->getMessage()}");
            return 1;
        }
    }

    private function watchLoop(): int
    {
        $interval = (int) $this->option('interval');
        $this->info('🔄 Iniciando monitoramento em loop...');
        $this->info("⏱️ Intervalo: {$interval}s");
        $this->info('⏹️ Pressione Ctrl+C para parar');

        $count = 0;
        while (true) {
            try {
                $stats = $this->watcher->watchAll();
                $count++;

                $timestamp = now()->format('H:i:s');
                $total = $stats['total'];
                
                if ($total > 0) {
                    $this->line("[{$timestamp}] ✅ {$total} logs | System:{$stats['system_logs']} Caixa:{$stats['caixa_logs']} Cancel:{$stats['cancelamento_logs']}");
                } else {
                    $this->line("[{$timestamp}] ℹ️ Nenhum novo log");
                }

                sleep($interval);
            } catch (\Exception $e) {
                $this->error("❌ [{$timestamp}] Erro: {$e->getMessage()}");
                sleep($interval);
            }
        }

        return 0;
    }

    private function showStatus(): int
    {
        $this->info('📊 Status do LogWatcher:');

        try {
            $status = $this->watcher->getStatus();

            $this->table(
                ['Tipo', 'Último ID', 'Total Registros', 'Não Processados'],
                [
                    [
                        'System (USUARIOS_LOG)',
                        $status['system_last_id'],
                        $status['system_total'],
                        $status['system_total'] - $status['system_last_id'],
                    ],
                    [
                        'Caixa (LOG_CAIXA)',
                        $status['caixa_last_id'],
                        $status['caixa_total'],
                        $status['caixa_total'] - $status['caixa_last_id'],
                    ],
                    [
                        'Cancelamento (LOG_CANCELAMENTO)',
                        $status['cancelamento_last_id'],
                        $status['cancelamento_total'],
                        $status['cancelamento_total'] - $status['cancelamento_last_id'],
                    ],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Erro ao obter status: {$e->getMessage()}");
            return 1;
        }
    }
}
