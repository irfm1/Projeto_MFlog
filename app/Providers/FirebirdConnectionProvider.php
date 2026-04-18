<?php

namespace App\Providers;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use PDO;

class FirebirdConnectionProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Registrar o factory para conexão Firebird em AppServiceProvider
    }
    
    /**
     * Criar conexão Firebird usando PDO direto
     */
    public static function createConnection($config)
    {
        $dsn = sprintf(
            'firebird:dbname=%s:%s',
            $config['host'],
            $config['database']
        );
        
        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_CASE => PDO::CASE_UPPER,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            ]
        );
        
        return $pdo;
    }
}
