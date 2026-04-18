<?php

namespace Database\Factories;

use App\Models\CaixaLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaixaLogFactory extends Factory
{
    protected $model = CaixaLog::class;

    public function definition(): array
    {
        $tiposMovimentacao = [
            'REALIZOU UMA VENDA Nº: ' . $this->faker->numberBetween(80000, 90000),
            'ABERTURA DE CAIXA',
            'FECHAMENTO DE CAIXA',
            'DEVOLUÇÃO DE VENDA Nº: ' . $this->faker->numberBetween(80000, 90000),
        ];
        
        $descricao = $this->faker->randomElement($tiposMovimentacao);
        
        return [
            'COD_USUARIO' => $this->faker->numberBetween(1, 19),
            'COD_CAIXA' => $this->faker->numberBetween(1, 5),
            'NOME_PC' => 'DESKTOP-' . $this->faker->bothify('?????'),
            'VALOR_MOVIMENTADO' => $this->faker->randomFloat(2, 10, 500),
            'DESCRICAO' => $descricao,
            'DATA_HORA' => $this->faker->dateTimeBetween('-6 months')->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Estado para venda
     */
    public function venda(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'DESCRICAO' => 'REALIZOU UMA VENDA Nº: ' . $this->faker->numberBetween(80000, 90000),
                'VALOR_MOVIMENTADO' => $this->faker->randomFloat(2, 50, 500),
            ];
        });
    }
    
    /**
     * Estado para abertura
     */
    public function abertura(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'DESCRICAO' => 'ABERTURA DE CAIXA',
                'VALOR_MOVIMENTADO' => 0,
            ];
        });
    }
    
    /**
     * Estado para fechamento
     */
    public function fechamento(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'DESCRICAO' => 'FECHAMENTO DE CAIXA',
                'VALOR_MOVIMENTADO' => $this->faker->randomFloat(2, 100, 2000),
            ];
        });
    }
}
