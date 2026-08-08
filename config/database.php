<?php
/**
 * Class Database Handler (PDO)
 */
class Database {
    private string $host = "autorack.proxy.rlwy.net"; // Ganti dengan Host dari menu Connect Railway
    private string $db_name = "railway";
    private string $username = "root";
    private string $password = "yhcuQsJefetchMuXnlyYCGYtrQsqbKiv";
    private string $port = "14232"; // Ganti dengan Port dari menu Connect Railway
    private ?PDO $conn = null;

    public function getConnection(): PDO {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $exception) {
                die("Koneksi Database Gagal: " . $exception->getMessage());
            }
        }
        return $this->conn;
    }
}