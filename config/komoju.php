<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook secret token
    |--------------------------------------------------------------------------
    |
    | When sending webhook events, Komoju computes a SHA-2 HMAC signature using the provided secret token on dashboard
    | Use this token to verify X-Komoju-Signature before further process
    |
    */

    'webhook_secret_token' => env('KOMOJU_WEBHOOK_SECRET_TOKEN')

];
