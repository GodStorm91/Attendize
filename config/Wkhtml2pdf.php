<?php

return [

    'debug'       => env('APP_DEBUG_PDF', false),
    'binpath'     => '/usr/local/bin',
    'binfile'     => env('WKHTML2PDF_BIN_FILE', 'wkhtmltopdf'),
    'output_mode' => 'I',
];
