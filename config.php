<?php
// config.php - Configuración para HostGator con credenciales reales
return [
    'paypal' => [
        'client_id' => 'AaURHUsuJaAQ4Jt_Cenr4dkDox7HYri8FyANn47ge6ZeK-c0ESlhY44WCRnjC-2AjCG5Piku7kLZLK_1',
        'client_secret' => 'TU_PAYPAL_CLIENT_SECRET', // CAMBIAR: Obtener desde developers.paypal.com
        'mode' => 'sandbox', // CAMBIAR a 'live' para producción
        'webhook_id' => 'TU_WEBHOOK_ID' // CAMBIAR: ID del webhook de PayPal
    ],
    'mercadopago' => [
        'access_token' => 'TU_MERCADOPAGO_ACCESS_TOKEN', // CAMBIAR: Desde developers.mercadopago.com
        'public_key' => 'TU_MERCADOPAGO_PUBLIC_KEY', // CAMBIAR: Clave pública
        'webhook_secret' => 'TU_WEBHOOK_SECRET' // CAMBIAR: Secret para validar webhooks
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'ferna474_bienestar',
        'user' => 'ferna474_admin',
        'pass' => '.Uruguay.2046'
    ],
    'email' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'username' => 'demeisclaudia@gmail.com', // CAMBIAR: Tu email de Gmail
        'password' => 'tu_app_password', // CAMBIAR: Contraseña de aplicación de Gmail
        'from_email' => 'demeisclaudia@gmail.com', // CAMBIAR: Mismo email
        'from_name' => 'Bienestar Floral - Claudia De Meis'
    ],
    'site' => [
        'base_url' => 'https://bienestarfloral.com', // CAMBIAR: Tu dominio real de HostGator
        'name' => 'Bienestar Floral',
        'contact_instagram' => '@claudia.de.meis.psicoterapeuta',
        'contact_email' => 'demeisclaudia@gmail.com',
        'contact_phone' => '+598 092 912 456'
    ]
];
?>