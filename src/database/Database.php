<?php

/**
 * Database connection handler
 */
class Database
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
            require_once __DIR__ . '/../../config.php';
            
            global $dbHost, $dbPort, $dbName, $dbUsername, $dbPassword;
            
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";

            self::$connection = new PDO($dsn, $dbUsername, $dbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        }
        
        return self::$connection;
    }
}
