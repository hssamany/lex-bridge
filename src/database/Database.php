<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Database;

use PDO;
use Luxullus\LexBridge\Logger;

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
                Logger::log(
                    'Database',
                    'Connection attempt: host=%s, port=%s, db=%s, user=%s, env=%s',
                    $dbHost ?? 'null',
                    $dbPort ?? 'null',
                    $dbName ?? 'null',
                    $dbUsername ?? 'null',
                    $appEnv ?? 'null'
                );
                
                if (empty($dbHost) || empty($dbName)) {
                    throw new \PDOException('Database configuration is incomplete. Check config.php settings.');
                }
                

                $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";

                self::$connection = new PDO($dsn, $dbUsername, $dbPassword, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                
                Logger::info('Database connection successful', 'Database');
                
            } catch (\PDOException $e) {
                Logger::exception($e, 'Database Connection Failed');
                throw new \PDOException('Database connection failed. Please check configuration.');
            }
        }
        
        return self::$connection;
    }
}
