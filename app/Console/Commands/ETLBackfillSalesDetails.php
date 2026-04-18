<?php

namespace App\Console\Commands;

use App\Services\ETL\ETLService;
use Illuminate\Console\Command;

class ETLBackfillSalesDetails extends Command
{
    protected $signature = 'etl:backfill-sales-details {--limit= : Limita a quantidade de logs para backfill}';

    protected $description = 'Enriquece logs de caixa já carregados com venda, cliente e itens da venda';

    public function handle(): int
    {
        $this->info('🛠️  Backfill de detalhes de venda em logs financeiros...');

        try {
            $inicio = microtime(true);
            $limite = $this->option('limit');

            $etl = new ETLService();
            $stats = $etl->backfillFinanceiroVendasDetalhes(
                $limite !== null ? (int) $limite : null
            );

            $tempo = round(microtime(true) - $inicio, 2);

            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['logs processados', $stats['processados'] ?? 0],
                    ['logs atualizados', $stats['atualizados'] ?? 0],
                    ['logs com itens sincronizados', $stats['itens_sincronizados'] ?? 0],
                ]
            );

            $this->info("✅ Backfill concluído em {$tempo}s");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Erro no backfill: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
