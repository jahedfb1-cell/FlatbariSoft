<?php

/**
 * The building keeps its money in separate funds. Every cash and bank entry carries one of these codes, and
 * each fund gets its own monthly statement of receipts and payments.
 */
return [
    'funds' => [
        'OPERATING' => ['bn' => 'সার্ভিস চার্জ হিসাব', 'en' => 'Service Charges'],
        'GAS' => ['bn' => 'এলপিজি গ্যাস হিসাব', 'en' => 'LPG Gas Bill'],
        'CAPITAL' => ['bn' => 'প্রজেক্ট হিসাব', 'en' => 'Project Account'],
        'SINKING' => ['bn' => 'সঞ্চিত তহবিল', 'en' => 'Sinking Fund'],
    ],
];
