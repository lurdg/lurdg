<?php
declare(strict_types=1);

return [
    'base_url' => 'https://afrilinkglobal.com',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_password' => '',
    // Generate each secret independently with bin2hex(random_bytes(32)).
    'app_key' => '',
    'setup_key' => '',
    // Use only for local development.
    'allow_http' => false,
    // Provide an HTTPS Nominatim-compatible endpoint, or leave empty.
    'geocoder_url' => '',
    'geocoder_contact' => '',
    'smtp' => [
        'enabled' => false,
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => 'Afrilink Global Logistics',
    ],
];
