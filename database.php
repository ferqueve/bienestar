<?php
// database.php - Conexión a base de datos para HostGator
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
            // Log error pero no exponer credenciales
            error_log("Database connection failed: Connection error");
            throw new Exception("Database connection failed");
        }
    }

    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(100) NOT NULL,
            payment_method ENUM('paypal', 'mercadopago') NOT NULL,
            payment_id VARCHAR(100) DEFAULT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            total_amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
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
        } catch (PDOException $e) {
            error_log("Table creation failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    // Método para probar la conexión
    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>