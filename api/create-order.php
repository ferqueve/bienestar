<?php
// api/create-order.php - VERSIÓN CORREGIDA Y SIMPLIFICADA
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Logging para debugging
$input_raw = file_get_contents('php://input');
error_log("CREATE ORDER - Raw input: " . $input_raw);

$input = json_decode($input_raw, true);

if (!$input) {
    error_log("CREATE ORDER - JSON decode failed");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

if (!isset($input['items']) || !isset($input['payment_method'])) {
    error_log("CREATE ORDER - Missing required fields");
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: items or payment_method']);
    exit;
}

try {
    // Cargar base de datos
    if (!file_exists('../database.php')) {
        throw new Exception('Database configuration file not found');
    }
    
    require_once '../database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // Generar ID único de orden
    $orderId = 'BF-' . date('Ymd') . '-' . time() . '-' . rand(100, 999);
    $totalAmount = 0;
    
    // Validar y calcular total
    if (!is_array($input['items']) || empty($input['items'])) {
        throw new Exception('Invalid items array');
    }
    
    foreach ($input['items'] as $item) {
        if (!isset($item['price']) || !isset($item['quantity'])) {
            throw new Exception('Invalid item format');
        }
        $totalAmount += floatval($item['price']) * intval($item['quantity']);
    }
    
    // Determinar configuración según método de pago
    $paymentMethod = $input['payment_method'];
    $status = 'pending';
    $currency = 'UYU';
    
    switch ($paymentMethod) {
        case 'paypal':
            $currency = 'USD';
            break;
        case 'mercadopago':
            $currency = 'UYU';
            break;
        case 'bank_transfer':
            $currency = 'ARS';
            $status = 'pending_transfer'; // Usar estado genérico
            break;
        default:
            throw new Exception('Invalid payment method: ' . $paymentMethod);
    }
    
    // Preparar datos de la orden
    $customerEmail = isset($input['customer_email']) ? trim($input['customer_email']) : '';
    $customerName = isset($input['customer_name']) ? trim($input['customer_name']) : '';
    $customerPhone = isset($input['customer_phone']) ? trim($input['customer_phone']) : '';
    
    error_log("CREATE ORDER - Attempting to insert order: " . $orderId);
    
    // Insertar en base de datos - USAR SOLO ESTADOS BÁSICOS
    $stmt = $conn->prepare("
        INSERT INTO orders (
            order_id, payment_method, status, total_amount, currency, 
            customer_email, customer_name, customer_phone, items, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $itemsJson = json_encode($input['items']);
    
    $success = $stmt->execute([
        $orderId,
        $paymentMethod,
        $status,
        $totalAmount,
        $currency,
        $customerEmail,
        $customerName,
        $customerPhone,
        $itemsJson
    ]);
    
    if (!$success) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Database insert failed: ' . implode(' ', $errorInfo));
    }
    
    error_log("CREATE ORDER - Order inserted successfully: " . $orderId);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'order_id' => $orderId,
        'status' => $status,
        'total_amount' => $totalAmount,
        'currency' => $currency
    ];
    
    // Si es MercadoPago, intentar crear preferencia (opcional)
    if ($paymentMethod === 'mercadopago') {
        try {
            if (file_exists('../MercadoPagoHandler.php')) {
                require_once '../MercadoPagoHandler.php';
                $mpHandler = new MercadoPagoHandler($db);
                $preference = $mpHandler->createPreference([
                    'order_id' => $orderId,
                    'total_amount' => $totalAmount,
                    'items' => $input['items'],
                    'customer_email' => $customerEmail
                ]);
                
                if ($preference && isset($preference['init_point'])) {
                    $response['init_point'] = $preference['init_point'];
                    $response['preference_id'] = $preference['id'];
                }
            }
        } catch (Exception $e) {
            error_log("CREATE ORDER - MercadoPago error (non-fatal): " . $e->getMessage());
            // No fallar por MercadoPago, la orden ya se guardó
        }
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("CREATE ORDER - Database error: " . $e->getMessage());
    
    // Error específico para estados no válidos
    if (strpos($e->getMessage(), 'status') !== false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database configuration error',
            'message' => 'Invalid order status in database schema',
            'fix' => 'UPDATE database schema to support order statuses'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error',
            'message' => 'Failed to save order to database'
        ]);
    }
    
} catch (Exception $e) {
    error_log("CREATE ORDER - General error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>