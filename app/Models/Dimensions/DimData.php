<?php

namespace App\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimData extends Model
{
    protected $table = 'dim_data';
    protected $primaryKey = 'data_key';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'data_key',
        'data_completa',
        'dia_mes',
        'mes',
        'ano',
        'trimestre',
        'dia_semana',
        'mes_nome',
        'eh_fim_semana',
        'eh_feriado',
    ];

    /**
     * Cria chave de data no formato YYYYMMDD
     */
    public static function gerarChave(\Carbon\Carbon $data): int
    {
        return (int)$data->format('Ymd');
    }

    /**
     * Busca ou cria dimensão de data
     */
    public static function buscarOuCriar(\Carbon\Carbon $data)
    {
        $chave = self::gerarChave($data);
        
        return self::firstOrCreate(
            ['data_key' => $chave],
            [
                'data_completa' => $data->toDateString(),
                'dia_mes' => $data->day,
                'mes' => $data->month,
                'ano' => $data->year,
                'trimestre' => ceil($data->month / 3),
                'dia_semana' => $data->getTranslatedDayName('pt_BR'),
                'mes_nome' => $data->getTranslatedMonthName('pt_BR'),
                'eh_fim_semana' => $data->isWeekend(),
            ]
        );
    }
}
