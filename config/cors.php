<?php

return [
    'paths' => ['api/*', 'storage/*', 'download/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // Use '*' to allow all origins or specify your domain, e.g., 'http://localhost:8080'
    'allowed_headers' => ['*'],
    'allowed_origins_patterns' => [],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];