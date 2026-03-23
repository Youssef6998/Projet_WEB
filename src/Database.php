<?php
require 'vendor/autoload.php';
class Database {
    private static ?PDO $instance = null;

    private static string $host     = 'localhost';
    private static string $dbname   = 'stageconnect';
    private static string $user     = 'Ryad';       
    private static string $password = 'Rkraiss1976.';           

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . self::$host . ';dbname=' . self::$dbname . ';charset=utf8mb4';
            self::$instance = new PDO($dsn, self::$user, self::$password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$instance;
    }
}
