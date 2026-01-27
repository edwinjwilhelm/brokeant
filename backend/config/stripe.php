<?php
// Stripe Configuration - Phase 4
// NOTE: These are TEST keys. Replace with LIVE keys when Stripe approves your account.

return [
    'mode' => 'test', // Change to 'live' when ready
    
    // TEST KEYS (Sandbox)
    'test' => [
        'publishable_key' => 'pk_test_51SuHNOCJ7AoBA52optOwCCm1VleG70TBvfSfVjMLISSegjEM6FpoZKJdyxRvancwmTxVe98P3YqnlkAaT3F32juu00qjnb1xWs',
        'secret_key' => 'sk_test_51SuHNOCJ7AoBA52o5ksBPypq8QSGVJDN6ocqvlCmQ6dqdWYmpBfWHeCVr347I4906IKVXliCvvor2LlD2kjUQHvF005S0JQXsu',
        'webhook_secret' => '', // Set after webhook is configured
    ],
    
    // LIVE KEYS (Production - get after Stripe verifies your business)
    'live' => [
        'publishable_key' => '', // Will be populated after Stripe verification
        'secret_key' => '', // Will be populated after Stripe verification
        'webhook_secret' => '', // Set after webhook is configured
    ],
    
    // Payment settings
    'payment' => [
        'amount' => 210, // $2.10 CAD in cents (includes 5% GST)
        'currency' => 'cad',
        'description' => 'BrokeAnt Marketplace - User Registration Fee',
    ],
];
?>
