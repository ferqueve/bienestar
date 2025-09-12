<?php
require_once '../database.php';
require_once '../PayPalHandler.php';

$payload = file_get_contents('php://input');
$headers = getallheaders();

if (!$payload) {
    http_response_code(400);
    exit;
}

try {
    $db = new Database();
    $paypalHandler = new PayPalHandler($db);
    
    $conn = $db->getConnection();
    $stmt = $conn->prepare("
        INSERT INTO notifications (notification_type, payload) 
        VALUES ('paypal_webhook', ?)
    ");
    $stmt->execute([$payload]);
    
    if ($paypalHandler->processWebhook($payload, $headers)) {
        http_response_code(200);
        echo "OK";
    } else {
        http_response_code(400);
        echo "Bad Request";
    }
    
} catch (Exception $e) {
    error_log("PayPal webhook error: " . $e->getMessage());
    http_response_code(500);
    echo "Internal Server Error";
}
?>
