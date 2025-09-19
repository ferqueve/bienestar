<?php
// api/verify-payment.php - Verificar pago con mejor manejo de errores
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://bienestarfloral.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../database.php';
require_once '../PayPalHandler.php';
require_once '../MercadoPagoHandler.php';

// Verificar si es JSON
if (empty($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['error' => 'Unsupported Media Type - Expected JSON']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['payment_id']) || !isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos. Se requieren payment_id y order_id.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener orden
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$input['order_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Orden no encontrada']);
        exit;
    }
    
    $paymentData = null;
    $isValid = false;
    
    if ($order['payment_method'] === 'paypal') {
        $paypalHandler = new PayPalHandler($db);
        $paymentData = $paypalHandler->verifyPayment($input['payment_id']);
        $isValid = $paymentData && $paymentData['status'] === 'COMPLETED';
        
        if (!$isValid) {
            error_log("PayPal verification failed for order: " . $input['order_id']);
        }
    } else if ($order['payment_method'] === 'mercadopago') {
        $mpHandler = new MercadoPagoHandler($db);
        $paymentData = $mpHandler->verifyPayment($input['payment_id']);
        $isValid = $paymentData && $paymentData['status'] === 'approved';
    }
    
    if ($isValid) {
        // Actualizar orden
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = 'completed', 
                payment_id = ?, 
                payment_data = ?,
                updated_at = NOW()
            WHERE order_id = ?
        ");
        $stmt->execute([
            $input['payment_id'], 
            json_encode($paymentData), 
            $input['order_id']
        ]);
        
        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'order_id' => $input['order_id'],
            'message' => 'Pago verificado correctamente'
        ]);
    } else {
        http_response_code(402);
        echo json_encode([
            'status' => 'failed',
            'verified' => false,
            'message' => 'Verificación de pago fallida'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Verify payment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
}
?>