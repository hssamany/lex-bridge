<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Database;

use PDO;

/**
 * Database connection handler
 */
final class Database
{
    private static ?PDO $connection = null;
    
    /**
     * Get database connection
     * 
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                require_once __DIR__ . '/../../config.php';
                
                global $dbHost, $dbPort, $dbName, $dbUsername, $dbPassword, $appEnv;
                
                // Log connection attempt (without password)
                error_log(sprintf(
                    'Database connection attempt: host=%s, port=%s, db=%s, user=%s, env=%s',
                    $dbHost ?? 'null',
                    $dbPort ?? 'null',
                    $dbName ?? 'null',
                    $dbUsername ?? 'null',
                    $appEnv ?? 'null'
                ));
                
                if (empty($dbHost) || empty($dbName)) {
                    throw new \PDOException('Database configuration is incomplete. Check config.php settings.');
                }
                

                $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";

                self::$connection = new PDO($dsn, $dbUsername, $dbPassword, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                
                error_log('Database connection successful');
            } catch (\PDOException $e) {
                // Log full exception details with stack trace
                error_log(sprintf(
                    'Database connection failed: %s (Code: %s, File: %s:%d)\nStack trace:\n%s',
                    $e->getMessage(),
                    $e->getCode(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                ));
                throw new \PDOException(
                    sprintf(
                        'Database connection failed: %s (DSN: mysql:host=%s;port=%s;dbname=%s)',
                        $e->getMessage(),
                        $dbHost ?? 'null',
                        $dbPort ?? 'null',
                        $dbName ?? 'null'
                    ),
                    (int)$e->getCode(),
                    $e
                );
            }
        }
        
        return self::$connection;
    }
}
