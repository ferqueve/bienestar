<?php
// api/admin-orders.php - Obtener lista de pedidos para admin
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener pedidos ordenados por fecha (más recientes primero)
    $stmt = $conn->prepare("
        SELECT 
            id,
            order_id,
            payment_method,
            payment_id,
            status,
            total_amount,
            currency,
            customer_email,
            customer_name,
            customer_phone,
            items,
            payment_data,
            notes,
            created_at,
            updated_at,
            completed_at
        FROM orders 
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    $orders = $stmt->fetchAll();
    
    // Calcular estadísticas
    $stats = [
        'total' => count($orders),
        'pending' => 0,
        'completed' => 0,
        'shipped' => 0,
        'failed' => 0,
        'revenue' => 0
    ];
    
    foreach ($orders as $order) {
        $stats[$order['status']]++;
        if ($order['status'] === 'completed' || $order['status'] === 'shipped') {
            $stats['revenue'] += floatval($order['total_amount']);
        }
    }
    
    // Formatear fechas para JavaScript
    foreach ($orders as &$order) {
        $order['created_at'] = date('c', strtotime($order['created_at']));
        if ($order['updated_at']) {
            $order['updated_at'] = date('c', strtotime($order['updated_at']));
        }
        if ($order['completed_at']) {
            $order['completed_at'] = date('c', strtotime($order['completed_at']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Admin orders error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>