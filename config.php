<?php
// config.php - Configuración FINAL para bienestarfloral.com
return [
    'paypal' => [
        'client_id' => 'AYIac8tPbTkYxGzj2OtDstHDrx2tI77pDzU1fKvhjNLddGbvUc--QCibuL0ralGg4O3NxI6nUTk3Sz70',
        'client_secret' => 'EGOqZrbh8a45Gp_pIP_Hj8mCOfTpKG00EBttEwUNcz2d0-x07YQY6Eylp15m5rKx2qnWfYZ46qQiZOY4', // CAMBIAR por tu secret real
        'mode' => 'live', // CAMBIAR a 'live' para producción
        'webhook_id' => '74T970749D892893J' 
    ],
    'mercadopago' => [
        'access_token' => 'APP_USR-5378648928929883-091916-cae10c5448bc29726af0ef1579500f4e-1351029190',
        'public_key' => 'APP_USR-d56404a3-9b94-4485-96af-443119e8fc7b',
        'client_id' => '5378648928929883',
        'client_secret' => '2DkC3KtHcgLtNAZldnkpeY2jwRl3Q4Nw',
        'webhook_secret' => 'TU_WEBHOOK_SECRET' // Nunca me lo dio
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