<?php
class Database {
    // Konfigurasi Supabase
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    // Constructor untuk fleksibilitas konfigurasi
    public function __construct($host = null, $db_name = null, $username = null, $password = null, $port = 5432) {
        $this->host = $host ?? 'aws-0-ap-southeast-1.pooler.supabase.com';
        $this->db_name = $db_name ?? 'postgres';
        $this->username = $username ?? 'postgres.tqilpyehwaaknppnpyah';
        $this->password = $password ?? 'Omtelolet123.';
        $this->port = $port;
    }

    public function getConnection() {
        $this->conn = null;

        try {
            // Koneksi PDO dengan konfigurasi lebih lengkap
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name};sslmode=require";
            
            $this->conn = new PDO($dsn, 
                $this->username, 
                $this->password, 
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true
                ]
            );
        } catch(PDOException $exception) {
            // Log error untuk keamanan
            $this->logError("Database Connection Error", $exception);
            
            // Pesan umum untuk pengguna
            throw new Exception("Koneksi database gagal. Silakan hubungi administrator.");
        }

        return $this->conn;
    }

    // Metode tambahan untuk keamanan
    public function closeConnection() {
        $this->conn = null;
    }

    // Fungsi untuk eksekusi query umum
    public function executeQuery($sql, $params = []) {
        if (!$this->conn) {
            throw new Exception("Koneksi database belum dibuat");
        }

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            $this->logError("Query Error", $e);
            throw $e;
        }
    }

    // Metode untuk insert dengan kembalian ID
    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(", ", $keys);
        $placeholders = implode(", ", array_map(function($key) { return ":$key"; }, $keys));
    
        // Tambahkan RETURNING id untuk PostgreSQL
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders}) RETURNING id";
    
        try {
            // Debug logging
            error_log("Insert SQL: " . $sql);
            error_log("Insert Data: " . json_encode($data));
    
            $stmt = $this->executeQuery($sql, $data);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug result
            error_log("Insert Result: " . json_encode($result));
            
            return $result['id'] ?? null;
        } catch(Exception $e) {
            // Detailed error logging
            error_log("Insert Error: " . $e->getMessage());
            error_log("Insert Trace: " . $e->getTraceAsString());
            
            return false;
        }
    }

    // Metode untuk update dengan validasi kolom
    public function update($table, $data, $where) {
        // Validasi input
        if (empty($data)) {
            throw new Exception("Data update tidak boleh kosong");
        }

        // Cek kolom yang valid
        $validColumns = $this->getTableColumns($table);
        $filteredData = array_intersect_key($data, array_flip($validColumns));

        if (empty($filteredData)) {
            throw new Exception("Tidak ada kolom valid untuk diupdate");
        }

        $updateFields = implode(", ", array_map(function($key) { 
            return "$key = :$key"; 
        }, array_keys($filteredData)));

        $sql = "UPDATE {$table} SET {$updateFields} WHERE {$where}";

        try {
            $stmt = $this->executeQuery($sql, $filteredData);
            return $stmt->rowCount();
        } catch(Exception $e) {
            error_log("Update Error: " . $e->getMessage());
            return false;
        }
    }

    // Metode untuk mendapatkan kolom tabel
    private function getTableColumns($table) {
        $sql = "SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = :table";
        
        try {
            $stmt = $this->executeQuery($sql, ['table' => $table]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) {
            error_log("Get Columns Error: " . $e->getMessage());
            return [];
        }
    }

    // Metode untuk select dengan kondisi opsional
    public function select($table, $conditions = [], $columns = '*') {
        $whereClause = '';
        $params = [];

        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $key => $value) {
                $whereParts[] = "$key = :$key";
                $params[$key] = $value;
            }
            $whereClause = "WHERE " . implode(' AND ', $whereParts);
        }

        $sql = "SELECT {$columns} FROM {$table} {$whereClause}";

        try {
            $stmt = $this->executeQuery($sql, $params);
            return $stmt->fetchAll();
        } catch(Exception $e) {
            error_log("Select Error: " . $e->getMessage());
            return false;
        }
    }

    // Metode logging error
    private function logError($context, $exception) {
        $errorMessage = "[{$context}] " . $exception->getMessage() . 
                        " in " . $exception->getFile() . 
                        " on line " . $exception->getLine();
        
        error_log($errorMessage);
    }

    // Fungsi transaksi
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollBack() {
        return $this->conn->rollBack();
    }

    // Metode untuk delete
    public function delete($table, $conditions) {
        if (empty($conditions)) {
            throw new Exception("Kondisi delete tidak boleh kosong");
        }

        $whereParts = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $whereParts[] = "$key = :$key";
            $params[$key] = $value;
        }

        $whereClause = "WHERE " . implode(' AND ', $whereParts);
        $sql = "DELETE FROM {$table} {$whereClause}";

        try {
            $stmt = $this->executeQuery($sql, $params);
            return $stmt->rowCount();
        } catch(Exception $e) {
            error_log("Delete Error: " . $e->getMessage());
            return false;
        }
    }
}