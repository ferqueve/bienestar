<?php
class MercadoPagoHandler {
    private $db;
    private $config;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->config = include 'config.php';
    }

    public function createPreference($orderData) {
        $url = 'https://api.mercadopago.com/checkout/preferences';
        
        $items = [];
        foreach ($orderData['items'] as $item) {
            $items[] = [
                'id' => $item['id'],
                'title' => $item['name'],
                'description' => $item['description'] ?? '',
                'category_id' => 'health',
                'quantity' => $item['quantity'],
                'currency_id' => 'UYU',
                'unit_price' => floatval($item['price'])
            ];
        }

        $preference = [
            'items' => $items,
            'payer' => [
                'name' => $orderData['customer_name'] ?? '',
                'email' => $orderData['customer_email'] ?? ''
            ],
            'back_urls' => [
                'success' => $this->config['site']['base_url'] . '/payment-success',
                'failure' => $this->config['site']['base_url'] . '/payment-failure',
                'pending' => $this->config['site']['base_url'] . '/payment-pending'
            ],
            'auto_return' => 'approved',
            'external_reference' => $orderData['order_id'],
            'notification_url' => $this->config['site']['base_url'] . '/webhooks/mercadopago',
            'statement_descriptor' => 'Bienestar Floral'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['mercadopago']['access_token']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201 && $response) {
            return json_decode($response, true);
        }

        return null;
    }

    public function verifyPayment($paymentId) {
        $url = "https://api.mercadopago.com/v1/payments/{$paymentId}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['mercadopago']['access_token']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }

        return null;
    }

    public function processWebhook($payload) {
        $data = json_decode($payload, true);
        
        if (!$data || !isset($data['type'])) {
            return false;
        }

        if ($data['type'] === 'payment') {
            $paymentId = $data['data']['id'];
            $paymentInfo = $this->verifyPayment($paymentId);
            
            if ($paymentInfo) {
                return $this->updateOrderStatus($paymentInfo);
            }
        }

        return true;
    }

    private function updateOrderStatus($paymentInfo) {
        $status = 'pending';
        switch ($paymentInfo['status']) {
            case 'approved':
                $status = 'completed';
                break;
            case 'rejected':
            case 'cancelled':
                $status = 'failed';
                break;
        }

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "UPDATE orders SET status = ?, payment_data = ? WHERE order_id = ?"
        );
        
        return $stmt->execute([
            $status, 
            json_encode($paymentInfo), 
            $paymentInfo['external_reference']
        ]);
    }
}
?>
