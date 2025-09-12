<?php
// config.php - Configuración FINAL para bienestarfloral.com
return [
    'paypal' => [
        'client_id' => 'TU_CLIENT_ID',
        'client_secret' => 'TU_CLIENT_SECRET', // CAMBIAR por tu secret real
        'mode' => 'live', // CAMBIAR a 'live' para producción
        'webhook_id' => 'TU_WEBHOOK_ID' // Se configura después
    ],
    'mercadopago' => [
        'access_token' => 'TU_MERCADOPAGO_ACCESS_TOKEN', // Opcional: Solo si usarás MercadoPago
        'public_key' => 'TU_MERCADOPAGO_PUBLIC_KEY',
        'webhook_secret' => 'TU_WEBHOOK_SECRET'
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
        'username' => 'demeisclaudia@gmail.com', // CAMBIAR si usas otro email
        'password' => 'tu_app_password_gmail', // CAMBIAR: Generar contraseña de aplicación
        'from_email' => 'demeisclaudia@gmail.com',
        'from_name' => 'Bienestar Floral - Claudia De Meis'
    ],
    'site' => [
        'base_url' => 'https://bienestarfloral.com',
        'name' => 'Bienestar Floral',
        'contact_instagram' => '@claudia.de.meis.psicoterapeuta',
        'contact_email' => 'demeisclaudia@gmail.com',
        'contact_phone' => '+598 092 912 456'
    ]
];