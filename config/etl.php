<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ETL Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para o pipipeline ETL Firebird → SQLite
    |
    */

    'batch_size' => env('ETL_BATCH_SIZE', 1000),

    'incremental' => [
        'enabled' => env('ETL_INCREMENTAL_ENABLED', true),
        'interval' => env('ETL_INCREMENTAL_INTERVAL', 300), // segundos (5 min)
    ],

    'sources' => [
        'firebird' => 'firebird_prod',
        'sqlite' => env('DB_CONNECTION', 'sqlite'),
    ],

    'tables' => [
        'logs' => [
            'USUARIOS_LOG',
            'LOG_CAIXA',
            'LOG_CANCELAMENTO',
        ],
        'ref_data' => [
            'USUARIOS',
            'PROFISSIONAIS',
            'CLIENTES',
        ],
    ],

    'star_schema' => [
        'dimensions' => [
            'dim_usuario',
            'dim_data',
            'dim_modulo',
            'dim_tipo_operacao',
            'dim_cliente',
            'dim_profissional',
            'dim_usuario_alvo',
        ],
        'facts' => [
            'fato_logs_sistema',
            'fato_logs_financeiro',
        ],
    ],
];
