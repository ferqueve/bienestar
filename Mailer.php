<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

class Mailer {
    private $config;
    private $mailer;

    public function __construct() {
        $this->config = include 'config.php';
        $this->setupMailer();
    }

    private function setupMailer() {
        $this->mailer = new PHPMailer(true);
        
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['email']['smtp_host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['email']['username'];
        $this->mailer->Password = $this->config['email']['password'];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $this->config['email']['smtp_port'];
        $this->mailer->CharSet = 'UTF-8';
        
        $this->mailer->setFrom(
            $this->config['email']['from_email'], 
            $this->config['email']['from_name']
        );
    }

    public function sendOrderConfirmation($order, $paymentData = null) {
        try {
            $this->mailer->clearAddresses();
            
            if ($order['customer_email']) {
                $this->mailer->addAddress($order['customer_email']);
            }
            
            $this->mailer->addBCC('demeisclaudia@gmail.com');
            
            $this->mailer->Subject = 'Confirmación de pedido - Bienestar Floral #' . $order['order_id'];
            
            $items = json_decode($order['items'], true);
            $itemsHtml = '';
            
            foreach ($items as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $itemsHtml .= "
                    <tr>
                        <td>{$item['name']}</td>
                        <td>{$item['quantity']}</td>
                        <td>\${$item['price']}</td>
                        <td>\${$subtotal}</td>
                    </tr>
                ";
            }
            
            $this->mailer->isHTML(true);
            $this->mailer->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #ec4899; color: white; padding: 20px; text-align: center;'>
                        <h1>¡Gracias por tu pedido!</h1>
                        <p>Bienestar Floral - Claudia De Meis</p>
                    </div>
                    
                    <div style='padding: 20px;'>
                        <p>Tu pedido ha sido confirmado y procesado exitosamente.</p>
                        
                        <h3>Detalles del pedido:</h3>
                        <p><strong>Número de pedido:</strong> {$order['order_id']}</p>
                        <p><strong>Total:</strong> {$order['currency']} \${$order['total_amount']}</p>
                        <p><strong>Método de pago:</strong> " . ucfirst($order['payment_method']) . "</p>
                        
                        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                            <thead>
                                <tr style='background: #f8f9fa;'>
                                    <th style='padding: 10px; border: 1px solid #dee2e6;'>Producto</th>
                                    <th style='padding: 10px; border: 1px solid #dee2e6;'>Cantidad</th>
                                    <th style='padding: 10px; border: 1px solid #dee2e6;'>Precio</th>
                                    <th style='padding: 10px; border: 1px solid #dee2e6;'>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$itemsHtml}
                            </tbody>
                        </table>
                        
                        <div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <h4>Próximos pasos:</h4>
                            <p>Claudia se contactará contigo vía Instagram (@claudia.de.meis.psicoterapeuta) o WhatsApp para coordinar la entrega de tus productos.</p>
                            <p>Tiempo estimado de preparación: 2-3 días hábiles.</p>
                        </div>
                        
                        <hr style='margin: 30px 0;'>
                        
                        <div style='text-align: center; color: #666;'>
                            <p>¿Preguntas? Contáctanos:</p>
                            <p>📧 demeisclaudia@gmail.com</p>
                            <p>📱 WhatsApp: +598 092 912 456</p>
                            <p>📷 Instagram: @claudia.de.meis.psicoterapeuta</p>
                        </div>
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 15px; text-align: center; color: #666;'>
                        <p style='margin: 0;'>💜 Hecho con amor para tu bienestar emocional</p>
                    </div>
                </div>
            ";
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Mail error: " . $e->getMessage());
            return false;
        }
    }
}
?>