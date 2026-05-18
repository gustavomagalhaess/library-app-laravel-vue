<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dusk Environment Variables
    |--------------------------------------------------------------------------
    |
    | Here you may specify the environment variables that should be set when
    | running your Dusk tests. These variables will automatically be set for
    | you during the test execution, so you do not have to worry about
    | manually setting any of these before running your tests.
    |
    */

    'chrome_bin' => env('CHROME_BIN', '/usr/bin/chromium-browser'),
    'dusk_driver_url' => env('DUSK_DRIVER_URL', 'http://localhost:9515'),
];
