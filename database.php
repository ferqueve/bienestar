<?php
// database.php - Conexión corregida con soporte para transferencias bancarias
class Database {
    private $connection;
    private $config;

    public function __construct() {
        $this->config = include 'config.php';
        $this->connect();
        $this->createTables();
    }

    private function connect() {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                $this->config['database']['host'],
                $this->config['database']['name']
            );
            
            $this->connection = new PDO(
                $dsn,
                $this->config['database']['user'],
                $this->config['database']['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: Connection error");
            throw new Exception("Database connection failed");
        }
    }

    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(100) NOT NULL,
            payment_method ENUM('paypal', 'mercadopago', 'bank_transfer') NOT NULL,
            payment_id VARCHAR(100) DEFAULT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled', 'pending_payment', 'shipped') DEFAULT 'pending',
            total_amount DECIMAL(10,2) NOT NULL,
            total_amount_original DECIMAL(10,2) DEFAULT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            currency_original VARCHAR(3) DEFAULT NULL,
            exchange_rate DECIMAL(8,4) DEFAULT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            customer_name VARCHAR(255) DEFAULT NULL,
            customer_phone VARCHAR(50) DEFAULT NULL,
            items TEXT NOT NULL,
            payment_data TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY unique_order_id (order_id),
            KEY idx_payment_id (payment_id),
            KEY idx_status (status),
            KEY idx_customer_email (customer_email),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS exchange_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_currency VARCHAR(3) NOT NULL,
            to_currency VARCHAR(3) NOT NULL,
            rate DECIMAL(8,4) NOT NULL,
            source VARCHAR(50) DEFAULT 'manual',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_currency_pair (from_currency, to_currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(100) DEFAULT NULL,
            notification_type VARCHAR(50) NOT NULL,
            external_id VARCHAR(100) DEFAULT NULL,
            payload TEXT NOT NULL,
            processed TINYINT(1) DEFAULT 0,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            attempts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_order_id (order_id),
            KEY idx_notification_type (notification_type),
            KEY idx_processed (processed),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        try {
            $this->connection->exec($sql);
            
            // Insertar tasas de cambio iniciales
            $this->insertInitialExchangeRates();
            
        } catch (PDOException $e) {
            error_log("Table creation failed: " . $e->getMessage());
        }
    }

    private function insertInitialExchangeRates() {
        try {
            // Insertar o actualizar tasas de cambio iniciales
            $rates = [
                ['UYU', 'ARS', 0.65],  // 1 UYU = 0.65 ARS (aproximado)
                ['UYU', 'USD', 0.025], // 1 UYU = 0.025 USD (aproximado)
                ['ARS', 'UYU', 1.54],  // 1 ARS = 1.54 UYU (aproximado)
                ['USD', 'UYU', 40.0],  // 1 USD = 40 UYU (aproximado)
            ];

            $stmt = $this->connection->prepare("
                INSERT INTO exchange_rates (from_currency, to_currency, rate, source) 
                VALUES (?, ?, ?, 'initial') 
                ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate), 
                updated_at = CURRENT_TIMESTAMP
            ");

            foreach ($rates as $rate) {
                $stmt->execute($rate);
            }

        } catch (PDOException $e) {
            error_log("Exchange rates insertion failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Método para obtener tasa de cambio
    public function getExchangeRate($fromCurrency, $toCurrency) {
        try {
            $stmt = $this->connection->prepare("
                SELECT rate FROM exchange_rates 
                WHERE from_currency = ? AND to_currency = ? 
                ORDER BY updated_at DESC LIMIT 1
            ");
            $stmt->execute([$fromCurrency, $toCurrency]);
            $result = $stmt->fetch();
            
            return $result ? $result['rate'] : null;
        } catch (PDOException $e) {
            error_log("Exchange rate lookup failed: " . $e->getMessage());
            return null;
        }
    }

    // Método para actualizar tasa de cambio
    public function updateExchangeRate($fromCurrency, $toCurrency, $rate, $source = 'manual') {
        try {
            $stmt = $this->connection->prepare("
                INSERT INTO exchange_rates (from_currency, to_currency, rate, source) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                rate = VALUES(rate), 
                source = VALUES(source),
                updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$fromCurrency, $toCurrency, $rate, $source]);
        } catch (PDOException $e) {
            error_log("Exchange rate update failed: " . $e->getMessage());
            return false;
        }
    }
}
?>