<?php
class Database {
    private $conn;

    public function __construct() {
        $host = 'localhost';
        $database = 'trouidees_queue';
        $username = 'root';
        $password = '';

        $this->conn = new mysqli($host, $username, $password, $database, 3306);
        if ($this->conn->connect_error) {
            die('Database connection failed: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>
