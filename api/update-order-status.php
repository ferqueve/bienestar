<?php
// api/update-order-status.php - Actualizar estado de pedidos
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id or status']);
    exit;
}

// Validar status permitidos
$allowedStatuses = ['pending', 'completed', 'shipped', 'failed', 'cancelled'];
if (!in_array($input['status'], $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status value']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verificar que el pedido existe
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$input['order_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    // Actualizar estado
    $updateFields = ['status = ?', 'updated_at = CURRENT_TIMESTAMP'];
    $params = [$input['status']];
    
    // Si se marca como completed y no tenía completed_at, agregarlo
    if ($input['status'] === 'completed' && !$order['completed_at']) {
        $updateFields[] = 'completed_at = CURRENT_TIMESTAMP';
    }
    
    // Agregar notas si se proporcionan
    if (isset($input['notes']) && !empty($input['notes'])) {
        $updateFields[] = 'notes = ?';
        $params[] = $input['notes'];
    }
    
    $params[] = $input['order_id']; // Para el WHERE
    
    $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    // Verificar que se actualizó
    if ($stmt->rowCount() === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update order']);
        exit;
    }
    
    // Obtener orden actualizada
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$input['order_id']]);
    $updatedOrder = $stmt->fetch();
    
    // Log del cambio para auditoría
    error_log("Order status updated: {$input['order_id']} from {$order['status']} to {$input['status']}");
    
    // Enviar email al cliente si se marca como shipped (opcional)
    if ($input['status'] === 'shipped') {
        try {
            sendShippedNotification($updatedOrder);
        } catch (Exception $e) {
            error_log("Failed to send shipped notification: " . $e->getMessage());
            // No fallar la actualización por el email
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully',
        'order' => $updatedOrder
    ]);
    
} catch (Exception $e) {
    error_log("Update order status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}

// Función opcional para notificar envío por email
function sendShippedNotification($order) {
    if (!$order['customer_email']) {
        return;
    }
    
    $to = $order['customer_email'];
    $subject = "Tu pedido ha sido enviado - Bienestar Floral #{$order['order_id']}";
    $message = "
        Hola {$order['customer_name']},
        
        Te informamos que tu pedido #{$order['order_id']} ha sido enviado.
        
        Claudia se pondrá en contacto contigo para coordinar la entrega.
        
        ¡Gracias por elegir Bienestar Floral!
        
        Saludos,
        Claudia De Meis
        Instagram: @claudia.de.meis.psicoterapeuta
        WhatsApp: +598 092 912 456
    ";
    
    $headers = "From: Bienestar Floral <demeisclaudia@gmail.com>\r\n";
    $headers .= "Reply-To: demeisclaudia@gmail.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($to, $subject, $message, $headers);
}
?>