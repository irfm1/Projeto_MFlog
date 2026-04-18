<?php

namespace Database\Factories;

use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class SystemLogFactory extends Factory
{
    protected $model = SystemLog::class;

    public function definition(): array
    {
        $operacoes = ['VISUALIZAR', 'SALVAR', 'ADICIONAR', 'EXCLUIR', 'ABRIR MODULO'];
        $modulos = [
            'Menu da Aplicação',
            'Ficha do Profissional',
            'Movimentação de Estoque',
            'Cadastro de Cliente',
            'Vendas',
            'Caixa',
            'Relatórios',
        ];
        
        $tipoOperacao = $this->faker->randomElement($operacoes);
        $modulo = $this->faker->randomElement($modulos);
        
        return [
            'CODIGO_USUARIO' => $this->faker->numberBetween(1, 19),
            'TIPO_OPERACAO' => $tipoOperacao,
            'MODULO' => $modulo,
            'CODIGO_MOVIMENTADO' => $this->faker->boolean(30) ? $this->faker->numberBetween(1, 1000) : 0,
            'NOME_MOVIMENTADO' => $this->faker->boolean(30) ? $this->faker->words(3, true) : '',
            'DESCRICAO' => $this->descricaoParaOperacao($tipoOperacao, $modulo),
            'DATA' => $this->faker->dateTimeBetween('-1 year')->format('Y-m-d'),
            'HORA' => $this->faker->time('H:i:s'),
            'CODIGO_USUARIO_EXECUTOU' => $this->faker->numberBetween(1, 19),
        ];
    }
    
    private function descricaoParaOperacao(string $operacao, string $modulo): string
    {
        return match($operacao) {
            'VISUALIZAR' => "Visualizou $modulo",
            'SALVAR' => "Salvou dados em $modulo",
            'ADICIONAR' => "Adicionou novo registro em $modulo",
            'EXCLUIR' => "Excluiu registro de $modulo",
            'ABRIR MODULO' => "Abriu $modulo",
            default => "Realizou ação em $modulo",
        };
    }
}
