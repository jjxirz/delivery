<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $host = 'localhost';
    private static $db_name = 'delivery_db';
    private static $username = 'admin';
    private static $password = 'almas12';
    private static $conn;

    public static function getConnection() {
        self::$conn = null;
        try {
            self::$conn = new PDO("mysql:host=" . self::$host . ";dbname=" . self::$db_name, self::$username, self::$password);
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            die("Error de conexión: " . $exception->getMessage());
        }
        return self::$conn;
    }
}