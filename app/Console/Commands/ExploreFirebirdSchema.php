<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExploreFirebirdSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebird:explore {table?} {--format=text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Explorar schema de tabelas Firebird e exportar dados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table') ?? 'USUARIOS_LOG';
        $format = $this->option('format');
        
        $this->info("🔍 Explorando tabela: $table\n");
        
        try {
            // Contar registros
            $countResult = DB::connection('firebird_prod')
                ->selectOne("SELECT COUNT(*) as cnt FROM $table");
            $count = $countResult->CNT;
            
            // Pegar primeira coluna para descobrir estrutura
            $sample = DB::connection('firebird_prod')
                ->selectOne("SELECT FIRST 1 * FROM $table");
            
            if (!$sample) {
                $this->warn("❌ Nenhum registro encontrado em $table");
                return Command::FAILURE;
            }
            
            $this->line("✅ Total de registros: <fg=green>$count</>");
            $this->line("\n📋 Estrutura de Colunas:");
            
            $columns = (array) $sample;
            $rows = [];
            
            foreach ($columns as $col => $value) {
                $type = $this->getDataType($value);
                $rows[] = [
                    'Coluna' => $col,
                    'Tipo' => $type,
                    'Amostra' => substr((string)$value, 0, 40) ?: '[NULL]',
                ];
            }
            
            $this->table(['Coluna', 'Tipo', 'Amostra'], $rows);
            
            // Pegar mais amostras para análise
            $this->line("\n📊 Amostras Adicionais:");
            $samples = DB::connection('firebird_prod')
                ->select("SELECT FIRST 5 * FROM $table");
            
            foreach ($samples as $idx => $row) {
                $this->line("\n<fg=blue>Registro #" . ($idx + 1) . ":</>");
                foreach ((array) $row as $col => $value) {
                    $this->line("  <fg=cyan>$col:</> " . (is_null($value) ? '[NULL]' : substr((string)$value, 0, 60)));
                }
            }
            
            // Indexs
            $this->line("\n\n🔑 Índices da Tabela:");
            $indices = DB::connection('firebird_prod')
                ->select("
                    SELECT RDB\$INDEX_NAME
                    FROM RDB\$INDICES
                    WHERE RDB\$RELATION_NAME = '$table'
                ");
            
            if (count($indices) > 0) {
                foreach ($indices as $idx) {
                    $this->line("  - " . trim($idx->{'RDB$INDEX_NAME'}));
                }
            } else {
                $this->line("  Nenhum índice registrado");
            }
            
            // Exportar info para arquivo
            if ($format === 'json') {
                $this->exportJson($table, $columns, $count);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Erro: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function getDataType($value): string
    {
        if (is_null($value)) return 'UNKNOWN';
        if (is_bool($value)) return 'BOOLEAN';
        if (is_int($value)) return 'INTEGER';
        if (is_float($value)) return 'FLOAT';
        
        $str = (string) $value;
        if (strtotime($str) !== false) return 'DATETIME/DATE';
        
        return 'VARCHAR';
    }
    
    private function exportJson($table, $columns, $count): void
    {
        $data = [
            'table' => $table,
            'total_records' => $count,
            'columns' => array_keys($columns),
            'column_types' => array_map(
                fn($v) => $this->getDataType($v),
                $columns
            ),
        ];
        
        file_put_contents(
            base_path("docs/schema-{$table}.json"),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        $this->line("\n✅ Exportado para: <fg=green>docs/schema-{$table}.json</>");
    }
}
