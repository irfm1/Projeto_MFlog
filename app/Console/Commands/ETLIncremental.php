<?php

namespace App\Console\Commands;

use App\Services\ETL\ETLService;
use Illuminate\Console\Command;

class ETLIncremental extends Command
{
    protected $signature = 'etl:incremental';

    protected $description = 'Sincroniza apenas registros novos do Firebird (incremental)';

    public function handle()
    {
        $this->info('🔄 Sincronizando novos registros...');

        try {
            $startTime = microtime(true);

            $etl = new ETLService();
            $stats = $etl->incrementalLoad();

            $elapsed = round(microtime(true) - $startTime, 2);

            $this->table(
                ['Tipo', 'Novos Registros'],
                [
                    ['logs_sistema', $stats['logs_sistema'] ?? 0],
                    ['logs_financeiro', $stats['logs_financeiro'] ?? 0],
                ]
            );

            $this->info("✅ Sincronização incremental concluída em {$elapsed}s");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Erro: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
