<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Generic verification code
    |--------------------------------------------------------------------------
    |
    | This code is intended for controlled test-account setup. It is checked
    | before the normal SMS record, so it can be used without sending an SMS.
    | Keep it out of public documentation and change it through the environment
    | when the test code needs to be rotated.
    |
    */
    'generic_code' => env('PHONE_AUTH_GENERIC_CODE', '89001'),
];
