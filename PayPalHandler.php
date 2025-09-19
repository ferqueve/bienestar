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
        if ($this->accessToken && time() < $this->accessToken['expires']) {
            return $this->accessToken['token'];
        }

        $url = $this->config['paypal']['mode'] === 'sandbox' 
            ? 'https://api.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api.paypal.com/v1/oauth2/token';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->config['paypal']['client_id'] . ':' . $this->config['paypal']['client_secret'],
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            error_log("PayPal auth failed: HTTP $httpCode - $error");
            throw new Exception('Failed to get PayPal access token: ' . $error);
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            error_log("PayPal auth invalid response: " . $response);
            throw new Exception('Invalid PayPal auth response');
        }

        // Guardar token con tiempo de expiración
        $this->accessToken = [
            'token' => $data['access_token'],
            'expires' => time() + ($data['expires_in'] - 60) // 60 segundos de margen
        ];

        return $this->accessToken['token'];
    }

    public function verifyPayment($paymentId) {
        try {
            $accessToken = $this->getAccessToken();
            
            $url = $this->config['paypal']['mode'] === 'sandbox'
                ? "https://api.sandbox.paypal.com/v2/checkout/orders/{$paymentId}"
                : "https://api.paypal.com/v2/checkout/orders/{$paymentId}";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'Prefer: return=representation'
                ],
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("PayPal order verification failed: HTTP $httpCode - $error");
                return null;
            }

            $data = json_decode($response, true);
            
            // Verificación adicional del estado del pago
            if ($data['status'] !== 'COMPLETED') {
                error_log("PayPal order not completed: " . $data['status']);
                return null;
            }

            return $data;

        } catch (Exception $e) {
            error_log("PayPal verification error: " . $e->getMessage());
            return null;
        }
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
