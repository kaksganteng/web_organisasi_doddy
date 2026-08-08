<?php
/**
 * Class Database Handler (PDO - PostgreSQL Supabase)
 */
class Database {
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    private string $port;
    private ?PDO $conn = null;

    public function __construct() {
        // Otomatis membaca variabel lingkungan dari integrasi Vercel & Supabase
        $this->host = getenv('POSTGRES_HOST') ?: 'localhost';
        $this->db_name = getenv('POSTGRES_DATABASE') ?: 'postgres';
        $this->username = getenv('POSTGRES_USER') ?: 'postgres';
        $this->password = getenv('POSTGRES_PASSWORD') ?: '';
        $this->port = getenv('POSTGRES_PORT') ?: '5432';
    }

    public function getConnection(): PDO {
        if ($this->conn === null) {
            try {
                // Perhatikan perubahan dari "mysql:" menjadi "pgsql:"
                $this->conn = new PDO(
                    "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name,
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