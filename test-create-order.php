<?php
// test-create-order.php - Script para probar el endpoint
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Test Create Order</title><style>
body { font-family: Arial, sans-serif; padding: 20px; }
.success { color: green; background: #e8f5e8; padding: 10px; border-radius: 5px; }
.error { color: red; background: #ffe8e8; padding: 10px; border-radius: 5px; }
.info { color: blue; background: #e8f4ff; padding: 10px; border-radius: 5px; }
pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🧪 Test Create Order Endpoint</h1>";

// PASO 1: Verificar que los archivos existen
echo "<h2>📁 Verificación de archivos</h2>";

$files_to_check = [
    '../database.php' => 'Database connection',
    './create-order.php' => 'Create order endpoint'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $description: $file existe</div>";
    } else {
        echo "<div class='error'>❌ $description: $file NO EXISTE</div>";
    }
}

// PASO 2: Test de conexión a base de datos
echo "<h2>🗄️ Test de Base de Datos</h2>";

try {
    require_once '../database.php';
    $db = new Database();
    $result = $db->testConnection();
    
    if ($result['success']) {
        echo "<div class='success'>✅ Conexión a BD exitosa</div>";
        echo "<div class='info'>Tablas encontradas: " . implode(', ', $result['tables']) . "</div>";
        
        // Verificar estructura de tabla orders
        $conn = $db->getConnection();
        $stmt = $conn->query("DESCRIBE orders");
        $columns = $stmt->fetchAll();
        
        echo "<h3>Estructura de tabla 'orders':</h3>";
        echo "<pre>";
        foreach ($columns as $col) {
            echo $col['Field'] . " - " . $col['Type'] . " - " . $col['Null'] . " - " . $col['Default'] . "\n";
        }
        echo "</pre>";
        
    } else {
        echo "<div class='error'>❌ Error de BD: " . $result['error'] . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error de BD: " . $e->getMessage() . "</div>";
}

// PASO 3: Test del endpoint create-order.php
echo "<h2>🚀 Test del Endpoint</h2>";

$testData = [
    'payment_method' => 'bank_transfer',
    'items' => [
        [
            'id' => 1,
            'name' => 'Rescue Remedy Test',
            'price' => 850,
            'quantity' => 1,
            'category' => 'Test'
        ]
    ],
    'customer_name' => 'Test User',
    'customer_email' => 'test@bienestarfloral.com',
    'customer_phone' => '+598 99 123 456'
];

echo "<h3>📤 Datos enviados:</h3>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Hacer la llamada al endpoint
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://bienestarfloral.com/api/create-order.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h3>📥 Respuesta del servidor:</h3>";
echo "<div class='info'><strong>HTTP Code:</strong> $httpCode</div>";

if ($curlError) {
    echo "<div class='error'><strong>cURL Error:</strong> $curlError</div>";
}

echo "<div class='info'><strong>Respuesta:</strong></div>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Verificar si es JSON válido
$jsonResponse = json_decode($response, true);
if ($jsonResponse) {
    echo "<div class='info'><strong>JSON decodificado:</strong></div>";
    echo "<pre>" . json_encode($jsonResponse, JSON_PRETTY_PRINT) . "</pre>";
}

// Evaluación final
echo "<h2>📊 Evaluación</h2>";

if ($httpCode === 200 && $jsonResponse && isset($jsonResponse['success'])) {
    echo "<div class='success'><strong>🎉 SUCCESS:</strong> El endpoint funciona correctamente</div>";
    
    if (isset($jsonResponse['order_id'])) {
        echo "<div class='info'>Orden creada: <strong>" . $jsonResponse['order_id'] . "</strong></div>";
        
        // Limpiar orden de prueba
        try {
            $conn = $db->getConnection();
            $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
            $stmt->execute([$jsonResponse['order_id']]);
            echo "<div class='info'>✅ Orden de prueba eliminada</div>";
        } catch (Exception $e) {
            echo "<div class='error'>⚠️ No se pudo eliminar orden de prueba: " . $e->getMessage() . "</div>";
        }
    }
    
} else {
    echo "<div class='error'><strong>❌ ERROR:</strong> El endpoint tiene problemas</div>";
    
    echo "<h3>🔧 Posibles soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verificar que el archivo database.php tenga las credenciales correctas</li>";
    echo "<li>Asegurar que la tabla 'orders' existe en la base de datos</li>";
    echo "<li>Revisar los logs de error del servidor</li>";
    echo "<li>Verificar permisos de archivos (755 para directorios, 644 para archivos)</li>";
    echo "</ul>";
}

echo "<h2>🛠️ SQL para crear/corregir tabla orders</h2>";
echo "<pre style='background: #f0f8ff; border: 1px solid #ccc;'>";
echo "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL UNIQUE,
    payment_method VARCHAR(50) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'pending_payment', 'shipped') DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(5) NOT NULL,
    customer_email VARCHAR(255),
    customer_name VARCHAR(255),
    customer_phone VARCHAR(50),
    items TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);";
echo "</pre>";

echo "</body></html>";
?>