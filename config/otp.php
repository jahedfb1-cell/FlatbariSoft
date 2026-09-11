<?php

return [

    // Digits in a generated OTP code.
    'length' => (int) env('OTP_LENGTH', 6),

    // How long a generated code stays valid.
    'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),

    // Wrong-code guesses allowed before the code is rejected outright
    // (the user must then request a fresh one).
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

];
