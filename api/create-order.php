<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../database.php';
require_once '../MercadoPagoHandler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['items']) || !isset($input['payment_method'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $orderId = 'BF-' . time() . '-' . rand(1000, 9999);
    $totalAmount = 0;
    
    foreach ($input['items'] as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
    }
    
    $orderData = [
        'order_id' => $orderId,
        'payment_method' => $input['payment_method'],
        'total_amount' => $totalAmount,
        'currency' => $input['payment_method'] === 'paypal' ? 'USD' : 'UYU',
        'customer_email' => $input['customer_email'] ?? '',
        'customer_name' => $input['customer_name'] ?? '',
        'items' => $input['items']
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO orders (order_id, payment_method, total_amount, currency, customer_email, customer_name, items) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $orderData['order_id'],
        $orderData['payment_method'],
        $orderData['total_amount'],
        $orderData['currency'],
        $orderData['customer_email'],
        $orderData['customer_name'],
        json_encode($orderData['items'])
    ]);
    
    $response = ['order_id' => $orderId];
    
    if ($input['payment_method'] === 'mercadopago') {
        $mpHandler = new MercadoPagoHandler($db);
        $preference = $mpHandler->createPreference($orderData);
        
        if ($preference) {
            $response['init_point'] = $preference['init_point'];
            $response['preference_id'] = $preference['id'];
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create MercadoPago preference']);
            exit;
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Create order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>