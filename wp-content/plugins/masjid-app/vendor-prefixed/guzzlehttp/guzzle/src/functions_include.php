<?php

namespace Masjid_App\Dependencies;

// Don't redefine the functions if included multiple times.
if (!\function_exists('Masjid_App\Dependencies\GuzzleHttp\describe_type')) {
    require __DIR__ . '/functions.php';
}
