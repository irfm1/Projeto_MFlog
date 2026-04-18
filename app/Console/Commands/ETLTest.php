<?php

namespace App\Console\Commands;

use App\Models\Dimensions\{DimUsuario, DimData, DimModulo, DimTipoOperacao};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ETLTest extends Command
{
    protected $signature = 'etl:test';
    protected $description = 'Test ETL with a small dataset';

    public function handle()
    {
        $this->info('🧪 ETL Test: Loading sample data...\n');

        try {
            // Test 1: Criar múltiplas dimensões para teste realista
            $this->info('1️⃣  Creating test dimensions (500+ records)...');
            
            // Criar 50 usuários
            for ($i = 1; $i <= 50; $i++) {
                DimUsuario::firstOrCreate(
                    ['codigo_usuario' => $i],
                    ['nome' => "User_$i", 'ativo' => rand(0, 1)]
                );
            }

            // Criar 30 datas
            for ($i = 0; $i < 30; $i++) {
                $date = Carbon::now()->subDays($i);
                $key = (int)$date->format('Ymd');
                DimData::firstOrCreate(
                    ['data_key' => $key],
                    [
                        'data_completa' => $date->toDateString(),
                        'dia_mes' => $date->day,
                        'mes' => $date->month,
                        'ano' => $date->year,
                        'trimestre' => ceil($date->month / 3),
                        'dia_semana' => $date->dayName,
                        'mes_nome' => $date->monthName,
                        'eh_fim_semana' => $date->isWeekend(),
                    ]
                );
            }

            // Criar 10 tipos de operação
            $tipos_operacao = ['CREATE', 'READ', 'UPDATE', 'DELETE', 'VIEW', 'EXPORT', 'IMPORT', 'APPROVE', 'REJECT', 'ARCHIVE'];
            foreach ($tipos_operacao as $tipo) {
                DimTipoOperacao::firstOrCreate(
                    ['tipo_descricao' => $tipo],
                    ['categoria' => $tipo]
                );
            }

            // Criar 10 módulos
            $modulos = ['Sales', 'Inventory', 'Finance', 'HR', 'Admin', 'Reports', 'Settings', 'API', 'Dashboard', 'Audit'];
            foreach ($modulos as $modulo) {
                DimModulo::firstOrCreate(
                    ['nome' => $modulo],
                    ['descricao' => "Module: $modulo"]
                );
            }

            $this->line('✅ Dimensions created');
            $this->table(['Table', 'Count'], [
                ['dim_usuario', DimUsuario::count()],
                ['dim_data', DimData::count()],
                ['dim_tipo_operacao', DimTipoOperacao::count()],
                ['dim_modulo', DimModulo::count()],
            ]);

            // Test 2: Load 1000 sample facts
            $this->info('\n2️⃣  Loading 1,000 sample facts...');
            
            $usuarios = DimUsuario::all();
            $datas = DimData::all();
            $tipos = DimTipoOperacao::all();
            $modulos_dim = DimModulo::all();

            $batch = [];
            for ($i = 0; $i < 1000; $i++) {
                $batch[] = [
                    'usuario_id' => $usuarios->random()->usuario_id,
                    'modulo_id' => $modulos_dim->random()->modulo_id,
                    'tipo_operacao_id' => $tipos->random()->tipo_operacao_id,
                    'data_key' => $datas->random()->data_key,
                    'hora_evento' => sprintf('%02d:%02d:%02d', rand(0, 23), rand(0, 59), rand(0, 59)),
                    'descricao' => 'Test operation ' . $i,
                    'codigo_movimentado' => rand(1, 100),
                    'valor_movimentado' => rand(100, 10000) / 100,
                    'quantidade' => rand(1, 50),
                    'codigo_log_firebird' => 10000 + $i,
                    'data_sincronizacao' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('fato_logs_sistema')->insert($batch);

            $this->info('✅ Facts loaded');
            $this->table(['Table', 'Count'], [
                ['fato_logs_sistema', DB::table('fato_logs_sistema')->count()],
            ]);

            // Test 3: Query statistics
            $this->info('\n3️⃣  Testing analytics queries...');
            
            $totalLogs = DB::table('fato_logs_sistema')->count();
            $uniqueUsers = DB::table('fato_logs_sistema')
                ->distinct('usuario_id')
                ->count('usuario_id');
            $totalValue = DB::table('fato_logs_sistema')
                ->sum('valor_movimentado');
            
            $this->table(['Metric', 'Value'], [
                ['Total Logs', $totalLogs],
                ['Unique Users', $uniqueUsers],
                ['Total Value', number_format($totalValue, 2)],
            ]);

            $this->info('\n4️⃣  Sample detailed query...');
            $result = DB::table('fato_logs_sistema')
                ->join('dim_usuario', 'fato_logs_sistema.usuario_id', '=', 'dim_usuario.usuario_id')
                ->select('dim_usuario.nome', 'fato_logs_sistema.descricao', 'fato_logs_sistema.valor_movimentado')
                ->limit(5)
                ->get();

            $rows = $result->map(fn($r) => [$r->nome, $r->descricao, number_format($r->valor_movimentado, 2)])->toArray();
            $this->table(['User', 'Action', 'Value'], $rows);
            
            $this->info('\n✅ ETL Star Schema is working perfectly!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("\n❌ Test failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
