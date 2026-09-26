<?php

return [

    'otp' => [
        'enabled' => (bool) env('OTP_ENABLED', true),
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    ],

    'password_expiry_days' => (int) env('PASSWORD_EXPIRY_DAYS', 90),

];
