<?php
class MercadoPagoHandler {
    private $db;
    private $config;
    private $exchangeRate;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->config = include 'config.php';
        $this->exchangeRate = $this->getExchangeRate();
    }

    /**
     * Obtener tasa UYU a ARS desde API o caché
     */
    private function getExchangeRate() {
        $cacheFile = 'cache/exchange_rate.json';
        $fallbackRate = 36.74; // Basado en tu última consulta

        // Verificar caché (válido por 1 hora)
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['rate'], $cached['timestamp'])) {
                $ageMinutes = (time() - $cached['timestamp']) / 60;
                if ($ageMinutes < 60) { // Caché válido por 1 hora
                    error_log("MercadoPago: Usando tasa desde caché: {$cached['rate']} (age: {$ageMinutes}min)");
                    return $cached['rate'];
                }
            }
        }

        // Obtener nueva tasa desde API
        try {
            error_log("MercadoPago: Obteniendo nueva tasa desde API...");
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'BienestarFloral/1.0'
                ]
            ]);

            $response = file_get_contents(
                'https://api.exchangerate-api.com/v4/latest/UYU', 
                false, 
                $context
            );

            if ($response) {
                $data = json_decode($response, true);
                
                if ($data && isset($data['rates']['ARS'])) {
                    $newRate = round($data['rates']['ARS'], 4);
                    
                    // Guardar en caché
                    if (!is_dir('cache')) {
                        mkdir('cache', 0755, true);
                    }
                    
                    $cacheData = [
                        'rate' => $newRate,
                        'timestamp' => time(),
                        'source' => 'exchangerate-api.com'
                    ];
                    
                    file_put_contents($cacheFile, json_encode($cacheData));
                    
                    error_log("MercadoPago: Nueva tasa obtenida: {$newRate} UYU = 1 ARS");
                    return $newRate;
                }
            }
        } catch (Exception $e) {
            error_log("MercadoPago: Error obteniendo tasa de API: " . $e->getMessage());
        }

        // Fallback: usar tasa hardcodeada
        error_log("MercadoPago: Usando tasa fallback: {$fallbackRate}");
        return $fallbackRate;
    }

    public function createPreference($orderData) {
        $url = 'https://api.mercadopago.com/checkout/preferences';
        
        $items = [];
        $totalUYU = 0;
        $totalARS = 0;

        foreach ($orderData['items'] as $item) {
            $priceUYU = floatval($item['price']);
            $priceARS = round($priceUYU * $this->exchangeRate, 2);
            $quantity = intval($item['quantity']);
            
            $totalUYU += $priceUYU * $quantity;
            $totalARS += $priceARS * $quantity;

            $items[] = [
                'id' => $item['id'],
                'title' => $item['name'],
                'description' => $item['description'] ?? '',
                'category_id' => 'health',
                'quantity' => $quantity,
                'currency_id' => 'ARS', // Cambiado de UYU a ARS
                'unit_price' => round($priceUYU * $this->exchangeRate, 2)  // Precio convertido usando API
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
            'statement_descriptor' => 'Bienestar Floral',
            'metadata' => [
                'original_currency' => 'UYU',
                'original_total' => $totalUYU,
                'converted_total' => $totalARS,
                'exchange_rate' => $this->exchangeRate,
                'conversion_date' => date('c')
            ]
        ];

        error_log("MercadoPago: Creando preferencia - Total UYU: {$totalUYU}, Total ARS: {$totalARS}, Tasa: {$this->exchangeRate}");

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
        $curlError = curl_error($ch);
        curl_close($ch);

        // Logging para debug
        if ($curlError) {
            error_log("MercadoPago: Error de cURL: {$curlError}");
            return null;
        }

        if ($httpCode !== 201) {
            error_log("MercadoPago: Error HTTP {$httpCode} - Response: {$response}");
            return null;
        }

        $result = json_decode($response, true);
        if ($result) {
            error_log("MercadoPago: Preferencia creada exitosamente - ID: " . ($result['id'] ?? 'unknown'));
            
            // Agregar info de conversión al resultado
            $result['conversion_info'] = [
                'original_total_uyu' => $totalUYU,
                'converted_total_ars' => $totalARS,
                'exchange_rate' => $this->exchangeRate,
                'conversion_date' => date('c')
            ];
        }

        return $result;
    }

    /**
     * Obtener información actual de la tasa (para frontend si es necesario)
     */
    public function getCurrentExchangeRate() {
        return [
            'rate' => $this->exchangeRate,
            'description' => "1 UYU = {$this->exchangeRate} ARS",
            'last_updated' => file_exists('cache/exchange_rate.json') ? 
                json_decode(file_get_contents('cache/exchange_rate.json'), true)['timestamp'] ?? null : null
        ];
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