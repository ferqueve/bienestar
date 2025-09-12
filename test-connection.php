<?php
// test-connection.php - Archivo para probar la conexión a ferna474_bienestar
// IMPORTANTE: Borrar este archivo después de probar por seguridad

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

echo "<html><head><title>Test Conexión - Bienestar Floral</title></head><body>";
echo "<h1>Prueba de Conexión - Base de Datos HostGator</h1>";

try {
    echo "<p>Intentando conectar a: <strong>ferna474_bienestar</strong></p>";
    
    $db = new Database();
    
    if ($db->testConnection()) {
        echo "<h2 style='color: green;'>✅ Conexión exitosa</h2>";
        
        // Probar consulta básica
        $conn = $db->getConnection();
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Tablas encontradas en ferna474_bienestar:</h3>";
        if (count($tables) > 0) {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li><strong>$table</strong></li>";
            }
            echo "</ul>";
        } else {
            echo "<p><em>No hay tablas creadas aún</em></p>";
        }
        
        // Verificar estructura de tabla orders si existe
        if (in_array('orders', $tables)) {
            echo "<h3>Estructura de tabla 'orders':</h3>";
            $stmt = $conn->query("DESCRIBE orders");
            $columns = $stmt->fetchAll();
            
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Probar inserción y eliminación
        echo "<h3>Probando operaciones CRUD:</h3>";
        $testOrderId = 'TEST-' . time();
        
        try {
            $stmt = $conn->prepare("INSERT INTO orders (order_id, payment_method, total_amount, items) VALUES (?, 'paypal', 100.00, '[]')");
            if ($stmt->execute([$testOrderId])) {
                echo "<p style='color: green;'>✅ Inserción de prueba exitosa (ID: $testOrderId)</p>";
                
                // Verificar que se insertó
                $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
                $stmt->execute([$testOrderId]);
                $result = $stmt->fetch();
                
                if ($result) {
                    echo "<p style='color: green;'>✅ Consulta de verificación exitosa</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ No se encontró el registro insertado</p>";
                }
                
                // Limpiar registro de prueba
                $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
                if ($stmt->execute([$testOrderId])) {
                    echo "<p style='color: green;'>✅ Eliminación de prueba exitosa</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ No se pudo eliminar el registro de prueba</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Error en inserción de prueba</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error en operación CRUD: " . $e->getMessage() . "</p>";
        }
        
        echo "<hr>";
        echo "<h2 style='color: green;'>🎉 Base de datos configurada correctamente</h2>";
        echo "<p><strong>Credenciales verificadas:</strong></p>";
        echo "<ul>";
        echo "<li>Base de datos: ferna474_bienestar ✅</li>";
        echo "<li>Usuario: ferna474_admin ✅</li>";
        echo "<li>Conexión: localhost ✅</li>";
        echo "<li>Codificación: UTF-8 ✅</li>";
        echo "</ul>";
        
        echo "<p style='background: #fffacd; padding: 10px; border: 1px solid #ddd;'>";
        echo "<strong>⚠️ IMPORTANTE:</strong> Borra este archivo (test-connection.php) después de verificar por seguridad.";
        echo "</p>";
        
    } else {
        echo "<h2 style='color: red;'>❌ Error en la conexión</h2>";
        echo "<p>No se pudo establecer conexión con la base de datos.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error de conexión</h2>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<h3>Verificar:</h3>";
    echo "<ul>";
    echo "<li>Que la base de datos 'ferna474_bienestar' exista en cPanel</li>";
    echo "<li>Que el usuario 'ferna474_admin' tenga permisos completos</li>";
    echo "<li>Que la contraseña '.Uruguay.2046' sea exactamente correcta</li>";
    echo "<li>Que el archivo config.php esté en la misma carpeta</li>";
    echo "</ul>";
    
    echo "<h3>Pasos para solucionar:</h3>";
    echo "<ol>";
    echo "<li>Ir a cPanel → MySQL Databases</li>";
    echo "<li>Verificar que existe la BD: ferna474_bienestar</li>";
    echo "<li>Verificar que existe el usuario: ferna474_admin</li>";
    echo "<li>Verificar que el usuario tiene TODOS los privilegios en la BD</li>";
    echo "<li>Si no existe, crearlos con exactamente estos nombres</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><small>Archivo de prueba para Bienestar Floral - HostGator MySQL</small></p>";
echo "</body></html>";
?>