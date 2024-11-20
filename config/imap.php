<?php

return ['default' => 'default',

    'accounts'        => [
        'default' => [
            'host'     => env('MAIL_HOST', 'imap.hostinger.com'), // Ensure this matches your IMAP host
            'port'     => env('MAIL_PORT', 993), // Common IMAP port for SSL
            'encryption' => env('MAIL_ENCRYPTION', 'ssl'), // Use SSL or TLS based on your IMAP settings
            'validate_cert' => true,
            'username' => env('MAIL_USERNAME', 'system@malishaedubd.com'), // IMAP username
            'password' => env('MAIL_PASSWORD', 'MalishaEduBDpass404!'), // IMAP password
            'protocol'   => 'imap', // Ensure 'imap' is used here
        ],
    ],

    'options'         => [
        'fetch' => [
            'body'        => true,
            'attachments' => true,
        ],
    ],
];