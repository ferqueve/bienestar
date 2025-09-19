<?php
// database-diagnostic.php - Verificar el estado actual de la base de datos
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico de Base de Datos - Bienestar Floral</h1>";

try {
    require_once 'database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>✅ Conexión a base de datos exitosa</h3>";
    echo "</div>";
    
    // 1. Verificar estructura actual de la tabla orders
    echo "<h2>1. Estructura actual de la tabla 'orders'</h2>";
    $stmt = $conn->query("DESCRIBE orders");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $statusColumn = null;
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td style='font-family: monospace;'>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
        
        if ($column['Field'] === 'status') {
            $statusColumn = $column;
        }
    }
    echo "</table>";
    
    // 2. Verificar específicamente el campo status
    echo "<h2>2. Análisis del campo 'status'</h2>";
    if ($statusColumn) {
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
        echo "<strong>Tipo actual:</strong> {$statusColumn['Type']}<br>";
        echo "<strong>¿Incluye 'pending_transfer'?</strong> ";
        
        if (strpos($statusColumn['Type'], 'pending_transfer') !== false) {
            echo "<span style='color: green; font-weight: bold;'>SÍ ✅</span>";
        } else {
            echo "<span style='color: red; font-weight: bold;'>NO ❌</span>";
            echo "<br><br><strong style='color: red;'>PROBLEMA IDENTIFICADO:</strong> El campo status no incluye 'pending_transfer'";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "<strong style='color: red;'>ERROR:</strong> No se encontró el campo 'status' en la tabla orders";
        echo "</div>";
    }
    
    // 3. Verificar si hay procesos bloqueados
    echo "<h2>3. Verificar procesos y bloqueos</h2>";
    try {
        $stmt = $conn->query("SHOW PROCESSLIST");
        $processes = $stmt->fetchAll();
        
        $blockedProcesses = array_filter($processes, function($process) {
            return stripos($process['State'], 'waiting') !== false || 
                   stripos($process['State'], 'locked') !== false ||
                   $process['Time'] > 300; // Más de 5 minutos
        });
        
        if (empty($blockedProcesses)) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
            echo "✅ No se detectaron procesos bloqueados";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
            echo "<strong>⚠️ Procesos sospechosos detectados:</strong><br>";
            foreach ($blockedProcesses as $process) {
                echo "ID: {$process['Id']}, Estado: {$process['State']}, Tiempo: {$process['Time']}s<br>";
            }
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
        echo "⚠️ No se puede verificar PROCESSLIST (normal en hosting compartido)";
        echo "</div>";
    }
    
    // 4. Test de inserción directa
    echo "<h2>4. Test de inserción directa</h2>";
    $testOrderId = 'DIAGNOSTIC-' . time();
    
    try {
        // Intentar inserción con status normal
        $stmt = $conn->prepare("INSERT INTO orders (order_id, payment_method, status, total_amount, currency, items) VALUES (?, 'bank_transfer', 'pending', 100.00, 'ARS', '[]')");
        if ($stmt->execute([$testOrderId . '-pending'])) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 5px 0;'>";
            echo "✅ Inserción con status 'pending': EXITOSA";
            echo "</div>";
            
            // Limpiar
            $conn->prepare("DELETE FROM orders WHERE order_id = ?")->execute([$testOrderId . '-pending']);
        }
        
        // Intentar inserción con pending_transfer
        $stmt = $conn->prepare("INSERT INTO orders (order_id, payment_method, status, total_amount, currency, items) VALUES (?, 'bank_transfer', 'pending_transfer', 100.00, 'ARS', '[]')");
        if ($stmt->execute([$testOrderId . '-transfer'])) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 5px 0;'>";
            echo "✅ Inserción con status 'pending_transfer': EXITOSA";
            echo "</div>";
            
            // Limpiar
            $conn->prepare("DELETE FROM orders WHERE order_id = ?")->execute([$testOrderId . '-transfer']);
        }
        
    } catch (PDOException $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "<strong style='color: red;'>❌ ERROR EN INSERCIÓN:</strong><br>";
        echo "Mensaje: " . htmlspecialchars($e->getMessage()) . "<br>";
        
        if (strpos($e->getMessage(), 'pending_transfer') !== false) {
            echo "<br><strong>CAUSA:</strong> El valor 'pending_transfer' no está permitido en el ENUM";
        }
        echo "</div>";
    }
    
    // 5. Solución recomendada
    echo "<h2>5. Solución recomendada</h2>";
    
    if (!$statusColumn || strpos($statusColumn['Type'], 'pending_transfer') === false) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
        echo "<h4>Ejecutar este SQL en phpMyAdmin:</h4>";
        echo "<code style='background: #f8f9fa; padding: 10px; display: block; font-family: monospace;'>";
        echo "ALTER TABLE orders MODIFY COLUMN status <br>";
        echo "ENUM('pending', 'completed', 'failed', 'cancelled', 'pending_transfer', 'shipped') <br>";
        echo "DEFAULT 'pending';";
        echo "</code>";
        echo "<br><strong>IMPORTANTE:</strong> Ejecuta esto exactamente en phpMyAdmin → SQL";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "✅ La estructura de la tabla está correcta";
        echo "<br>El problema puede estar en el código PHP o en la conexión";
        echo "</div>";
    }
    
    // 6. Verificar órdenes existentes
    echo "<h2>6. Órdenes recientes</h2>";
    $stmt = $conn->query("SELECT order_id, payment_method, status, total_amount, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
    $orders = $stmt->fetchAll();
    
    if ($orders) {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'><th>Order ID</th><th>Método</th><th>Status</th><th>Total</th><th>Fecha</th></tr>";
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>{$order['order_id']}</td>";
            echo "<td>{$order['payment_method']}</td>";
            echo "<td><strong>{$order['status']}</strong></td>";
            echo "<td>{$order['total_amount']}</td>";
            echo "<td>{$order['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No hay órdenes en la base de datos</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<strong style='color: red;'>ERROR DE CONEXIÓN:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<h2>Próximos pasos según el resultado:</h2>";
echo "<ul>";
echo "<li><strong>Si falta 'pending_transfer':</strong> Ejecutar el SQL de corrección</li>";
echo "<li><strong>Si hay procesos bloqueados:</strong> Contactar soporte de HostGator</li>";
echo "<li><strong>Si la estructura está bien:</strong> El problema está en create-order.php</li>";
echo "<li><strong>Si persisten errores:</strong> Revisar error_log de PHP</li>";
echo "</ul>";

echo "<p><small>Archivo: " . __FILE__ . " | Fecha: " . date('Y-m-d H:i:s') . "</small></p>";
?>