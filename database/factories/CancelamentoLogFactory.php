<?php

namespace Database\Factories;

use App\Models\CancelamentoLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class CancelamentoLogFactory extends Factory
{
    protected $model = CancelamentoLog::class;

    public function definition(): array
    {
        $tipos = ['Serviço', 'Produto'];
        $motivos = [
            'Dano durante o serviço',
            'Cliente insatisfeito com o resultado',
            'Produto com defeito',
            'Erro na emissão',
            'Solicitação do cliente',
            'Falha no atendimento',
            'Cancelamento preventivo',
            'Reembolso total',
        ];
        
        return [
            'COD_USUARIO' => $this->faker->numberBetween(1, 19),
            'COD_CLIENTE' => $this->faker->numberBetween(1, 500),
            'TIPO_CANCELAMENTO' => $this->faker->randomElement($tipos),
            'VALOR' => $this->faker->randomFloat(2, 50, 1500),
            'DATA_CANCELAMENTO' => $this->faker->dateTimeBetween('-3 months')->format('Y-m-d'),
            'MOTIVO' => $this->faker->randomElement($motivos),
        ];
    }
    
    /**
     * Estado para cancelamento de serviço
     */
    public function servico(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'TIPO_CANCELAMENTO' => 'Serviço',
                'VALOR' => $this->faker->randomFloat(2, 100, 800),
            ];
        });
    }
    
    /**
     * Estado para cancelamento de produto
     */
    public function produto(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'TIPO_CANCELAMENTO' => 'Produto',
                'VALOR' => $this->faker->randomFloat(2, 50, 400),
            ];
        });
    }
    
    /**
     * Estado para valores altos (acima de R$ 500)
     */
    public function altovalor(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'VALOR' => $this->faker->randomFloat(2, 500, 2000),
            ];
        });
    }
}
