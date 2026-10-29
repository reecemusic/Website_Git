<?php

return [
    'database' => [
        'host' => 'localhost',
        'name' => 'reecemus_subscriber_list',
        'user' => 'reecemus_admin',
        'password' => 'replace-with-cpanel-password',
        'charset' => 'utf8mb4'
    ],
    'smtp' => [
        'host' => 'mail.reecemusic.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => '_mainaccount@reecemusic.com',
        'password' => 'replace-with-cpanel-password',
        'from' => 'info@reecemusic.com'
    ],
    // MIDI store (midi.php)
    'midi' => [
        'stripe_secret_key' => 'replace-with-stripe-secret-key', // sk_test_… for testing, sk_live_… for real payments
        'token_secret' => 'replace-with-a-long-random-string',   // 32+ characters
        'files_dir' => '/home/reecemus/midi-files'               // private folder outside public_html
    ]
];
