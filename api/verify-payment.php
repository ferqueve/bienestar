<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../database.php';
require_once '../PayPalHandler.php';
require_once '../MercadoPagoHandler.php';
require_once '../Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['payment_id']) || !isset($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$input['order_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    $paymentData = null;
    $isValid = false;
    
    if ($order['payment_method'] === 'paypal') {
        $paypalHandler = new PayPalHandler($db);
        $paymentData = $paypalHandler->verifyPayment($input['payment_id']);
        $isValid = $paymentData && $paymentData['status'] === 'COMPLETED';
    } else if ($order['payment_method'] === 'mercadopago') {
        $mpHandler = new MercadoPagoHandler($db);
        $paymentData = $mpHandler->verifyPayment($input['payment_id']);
        $isValid = $paymentData && $paymentData['status'] === 'approved';
    }
    
    if ($isValid) {
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = 'completed', payment_id = ?, payment_data = ? 
            WHERE order_id = ?
        ");
        $stmt->execute([
            $input['payment_id'], 
            json_encode($paymentData), 
            $input['order_id']
        ]);
        
        $mailer = new Mailer();
        $mailer->sendOrderConfirmation($order, $paymentData);
        
        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'order_id' => $input['order_id']
        ]);
    } else {
        echo json_encode([
            'status' => 'failed',
            'verified' => false,
            'message' => 'Payment verification failed'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Verify payment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>