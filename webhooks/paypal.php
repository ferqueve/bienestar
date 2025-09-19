<?php
// webhooks/paypal.php - Webhook para PayPal en PRODUCCIÓN
// URL configurada en PayPal: https://bienestarfloral.com/webhooks/paypal

require_once '../database.php';
require_once '../PayPalHandler.php';

// Obtener payload y headers
$payload = file_get_contents('php://input');
$headers = getallheaders();

// Log para debugging (opcional - remover en producción)
error_log("PayPal Webhook received: " . date('Y-m-d H:i:s'));
error_log("Headers: " . json_encode($headers));
error_log("Payload: " . $payload);

if (!$payload) {
    http_response_code(400);
    error_log("PayPal Webhook: Empty payload received");
    exit('Bad Request - Empty payload');
}

try {
    $db = new Database();
    $paypalHandler = new PayPalHandler($db);
    
    // Registrar notificación en BD para auditoria
    $conn = $db->getConnection();
    $stmt = $conn->prepare("
        INSERT INTO notifications (notification_type, payload, external_id) 
        VALUES ('paypal_webhook', ?, ?)
    ");
    
    // Extraer ID del evento si existe
    $data = json_decode($payload, true);
    $eventId = $data['id'] ?? 'unknown';
    
    $stmt->execute([$payload, $eventId]);
    
    // Procesar webhook
    if ($paypalHandler->processWebhook($payload, $headers)) {
        http_response_code(200);
        error_log("PayPal Webhook processed successfully: " . $eventId);
        echo "OK";
    } else {
        http_response_code(400);
        error_log("PayPal Webhook processing failed: " . $eventId);
        echo "Bad Request - Processing failed";
    }
    
} catch (Exception $e) {
    error_log("PayPal webhook error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo "Internal Server Error";
}

// Opcional: Notificar por email en caso de errores críticos
function notifyError($message) {
    $config = include '../config.php';
    $to = $config['site']['contact_email'];
    $subject = 'Error en Webhook PayPal - Bienestar Floral';
    $body = "Error en webhook PayPal:\n\n" . $message . "\n\nFecha: " . date('Y-m-d H:i:s');
    
    mail($to, $subject, $body);
}
?>