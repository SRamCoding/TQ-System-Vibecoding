<?php

declare(strict_types=1);

// Observación 9.3: la landing de Techquk puede enviar su formulario con JavaScript (fetch) a la entrada de leads.
return [
    'paths' => ['entrada/leads'],
    'allowed_methods' => ['POST'],
    'allowed_origins' => array_filter(explode(',', (string) env('TECHQUK_ENTRADA_LEADS_ORIGENES', '*'))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Techquk-Clave'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
