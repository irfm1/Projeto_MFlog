<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestFirebirdConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebird:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testar conexão com Firebird';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testando conexão com Firebird...');
        
        try {
            $count = DB::connection('firebird_prod')->table('USUARIOS_LOG')->count();
            $this->info("✅ Conexão bem-sucedida!");
            $this->line("📊 Total de registros em USUARIOS_LOG: <fg=green>$count</>");
            
            // Usar raw query para contornar dialeto Firebird
            $sample = DB::connection('firebird_prod')
                ->selectOne('SELECT FIRST 1 * FROM USUARIOS_LOG');
            
            if ($sample) {
                $this->line("\n📝 Amostra de registro:");
                foreach ((array) $sample as $key => $value) {
                    $this->line("  <fg=cyan>$key:</>  $value");
                }
            }
            
            // Testar mais tabelas
            $this->line("\n📊 Contagens gerais:");
            $caixa = DB::connection('firebird_prod')->selectOne('SELECT COUNT(*) as cnt FROM LOG_CAIXA');
            $cancel = DB::connection('firebird_prod')->selectOne('SELECT COUNT(*) as cnt FROM LOG_CANCELAMENTO');
            $usuarios = DB::connection('firebird_prod')->selectOne('SELECT COUNT(*) as cnt FROM USUARIOS');
            
            $this->line("  LOG_CAIXA: <fg=green>{$caixa->CNT}</>");
            $this->line("  LOG_CANCELAMENTO: <fg=green>{$cancel->CNT}</>");
            $this->line("  USUARIOS: <fg=green>{$usuarios->CNT}</>");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro na conexão:");
            $this->error($e->getMessage());
            $this->line("\n<fg=yellow>Stack trace:</>");
            $this->line($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}
