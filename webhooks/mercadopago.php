<?php
require_once '../database.php';
require_once '../MercadoPagoHandler.php';

$payload = file_get_contents('php://input');

if (!$payload) {
    http_response_code(400);
    exit;
}

try {
    $db = new Database();
    $mpHandler = new MercadoPagoHandler($db);
    
    $conn = $db->getConnection();
    $stmt = $conn->prepare("
        INSERT INTO notifications (notification_type, payload) 
        VALUES ('mercadopago_webhook', ?)
    ");
    $stmt->execute([$payload]);
    
    if ($mpHandler->processWebhook($payload)) {
        http_response_code(200);
        echo "OK";
    } else {
        http_response_code(400);
        echo "Bad Request";
    }
    
} catch (Exception $e) {
    error_log("MercadoPago webhook error: " . $e->getMessage());
    http_response_code(500);
    echo "Internal Server Error";
}
?>
