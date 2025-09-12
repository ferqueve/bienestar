<?php
class PayPalHandler {
    private $db;
    private $config;
    private $accessToken;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->config = include 'config.php';
    }

    private function getAccessToken() {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $url = $this->config['paypal']['mode'] === 'sandbox' 
            ? 'https://api.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api.paypal.com/v1/oauth2/token';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, 
            $this->config['paypal']['client_id'] . ':' . $this->config['paypal']['client_secret']
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            $this->accessToken = $data['access_token'] ?? null;
            return $this->accessToken;
        }

        throw new Exception('Failed to get PayPal access token');
    }

    public function verifyPayment($paymentId) {
        $url = $this->config['paypal']['mode'] === 'sandbox'
            ? "https://api.sandbox.paypal.com/v2/checkout/orders/{$paymentId}"
            : "https://api.paypal.com/v2/checkout/orders/{$paymentId}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getAccessToken()
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }

        return null;
    }

    public function processWebhook($payload, $headers) {
        $data = json_decode($payload, true);
        
        if (!$data || !isset($data['event_type'])) {
            return false;
        }

        switch ($data['event_type']) {
            case 'CHECKOUT.ORDER.APPROVED':
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handlePaymentCompleted($data);
            case 'PAYMENT.CAPTURE.DENIED':
                return $this->handlePaymentFailed($data);
            default:
                error_log("PayPal webhook event: " . $data['event_type']);
                return true;
        }
    }

    private function handlePaymentCompleted($data) {
        $orderId = $data['resource']['id'] ?? null;
        if (!$orderId) return false;

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "UPDATE orders SET status = 'completed', payment_data = ? WHERE payment_id = ?"
        );
        
        return $stmt->execute([json_encode($data), $orderId]);
    }

    private function handlePaymentFailed($data) {
        $orderId = $data['resource']['id'] ?? null;
        if (!$orderId) return false;

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare(
            "UPDATE orders SET status = 'failed', payment_data = ? WHERE payment_id = ?"
        );
        
        return $stmt->execute([json_encode($data), $orderId]);
    }
}
?>
