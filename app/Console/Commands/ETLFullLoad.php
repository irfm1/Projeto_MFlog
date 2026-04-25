<?php

namespace App\Console\Commands;

use App\Services\ETL\ETLService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ETLFullLoad extends Command
{
    protected $signature = 'etl:full-load 
                          {--no-migrate : Não executar migrations}
                          {--max-batches= : Limita batches por tabela para teste rápido}
                          {--days= : Carrega apenas os últimos N dias (ex: 7, 15, 30)}';

    protected $description = 'Carrega todo o histórico do Firebird → SQLite com Star Schema';

    public function handle()
    {
        $this->info('🚀 Iniciando ETL Full Load - Firebird → SQLite');
        $this->line('');

        try {
            // 1. Executa migrations
            if (!$this->option('no-migrate')) {
                $this->info('📋 Executando migrations...');
                Artisan::call('migrate', ['--force' => true]);
                $this->line('✅ Migrations executadas\n');
            }

            // 2. Executa ETL
            $this->info('⚙️  Iniciando extração e transformação...');
            $this->line('');
            $startTime = microtime(true);

            $etl = new ETLService();
            $maxBatches = $this->option('max-batches');
            $days = $this->option('days');

            if ($days !== null && (!is_numeric($days) || (int) $days <= 0)) {
                $this->error('❌ A opção --days deve ser um número inteiro maior que zero.');
                return self::FAILURE;
            }

            if ($days !== null) {
                $this->line("📅 Período selecionado: últimos {$days} dias");
            }

            $stats = $etl->fullLoad(
                $maxBatches ? (int) $maxBatches : null,
                $days !== null ? (int) $days : null
            );

            $elapsed = round(microtime(true) - $startTime, 2);

            // 3. Exibe estatísticas
            $this->line('');
            $this->info('📊 Estatísticas do Carregamento:');
            $this->table(
                ['Dimensão/Fato', 'Registros Carregados'],
                [
                    ['dim_usuario', $stats['dim_usuario'] ?? 0],
                    ['dim_data', $stats['dim_data'] ?? 0],
                    ['dim_modulo', $stats['dim_modulo'] ?? 0],
                    ['dim_tipo_operacao', $stats['dim_tipo_operacao'] ?? 0],
                    ['dim_cliente', $stats['dim_cliente'] ?? 0],
                    ['dim_profissional', $stats['dim_profissional'] ?? 0],
                    ['dim_usuario_alvo', $stats['dim_usuario_alvo'] ?? 0],
                    ['fato_logs_sistema', $stats['fato_logs_sistema'] ?? 0],
                    ['fato_logs_financeiro', $stats['fato_logs_financeiro'] ?? 0],
                ]
            );

            // 4. Estatísticas finais
            $this->line('');
            $totalRegistros = ($stats['dim_usuario'] ?? 0) 
                            + ($stats['fato_logs_sistema'] ?? 0) 
                            + ($stats['fato_logs_financeiro'] ?? 0);
            
            $this->info("✨ ETL Full Load finalizado!");
            $this->line("📈 Total de registros processados: {$totalRegistros}");
            $this->line("⏱️  Tempo total: {$elapsed}s");
            $this->line("🚀 Velocidade: " . round($totalRegistros / $elapsed, 0) . " registros/segundo");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Erro durante ETL Full Load: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
