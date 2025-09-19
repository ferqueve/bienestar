<?php
// config.php - Configuración FINAL para bienestarfloral.com
return [
    'paypal' => [
        'client_id' => 'Aeik_Dpc4NKf4uIqfo1p-Iejnk_a5WHXhCVSFxZ_LBYX2-9NcGwmBQevu4IF6FwtXZ2r4aYg_r1dsrQl',
        'client_secret' => 'EBb89ySnKpF_FSj4zlMlQiiRbxRV8vMcSzK9Z2BEwaesEJd2PObCAHlHJ9tFhw_6_0sBgb-Jf9VIw845', // CAMBIAR por tu secret real
        'mode' => 'live', // CAMBIAR a 'live' para producción
        'webhook_id' => '2GL029296X733550S' // Se configura después
    ],
    'mercadopago' => [
        'access_token' => 'TU_MERCADOPAGO_ACCESS_TOKEN', // Opcional: Solo si usarás MercadoPago
        'public_key' => 'TU_MERCADOPAGO_PUBLIC_KEY',
        'webhook_secret' => 'TU_WEBHOOK_SECRET'
    ],
    'bank_transfer' => [
    'bank_name' => 'Banco Nación Argentina',
    'account_holder' => 'Claudia Noemi De Meis Acosta',
    'account_number' => 'CA $ 17602581066877',
    'cuil' => '27955713287',
    'cbu' => '0110258330025810668779',
    'alias' => 'clau.bienestar.bna',
    'currency' => 'ARS',
    'instructions' => 'Incluir número de pedido en el concepto de la transferencia'
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
?>