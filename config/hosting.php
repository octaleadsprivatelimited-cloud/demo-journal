<?php

declare(strict_types=1);

return [
    // Hostinger's public_html can sit beside the private Laravel application.
    'public_path' => env('APP_PUBLIC_PATH'),
    'initialize_sjnr' => env('SJNR_INITIALIZE_ALLOWED', false),
    'import_sjnr' => env('SJNR_IMPORT_ALLOWED', false),
];
