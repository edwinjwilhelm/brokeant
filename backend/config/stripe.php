<?php
// Stripe Configuration - Phase 4
// API keys are stored in environment variables for security

return [
    'mode' => getenv('STRIPE_MODE') ?: 'test', // 'test' or 'live'
    
    // TEST KEYS (loaded from environment variables)
    'test' => [
        'publishable_key' => getenv('STRIPE_TEST_PUBLIC_KEY') ?: '',
        'secret_key' => getenv('STRIPE_TEST_SECRET_KEY') ?: '',
        'webhook_secret' => getenv('STRIPE_TEST_WEBHOOK_SECRET') ?: '',
    ],
    
    // LIVE KEYS (loaded from environment variables after Stripe verification)
    'live' => [
        'publishable_key' => getenv('STRIPE_LIVE_PUBLIC_KEY') ?: '',
        'secret_key' => getenv('STRIPE_LIVE_SECRET_KEY') ?: '',
        'webhook_secret' => getenv('STRIPE_LIVE_WEBHOOK_SECRET') ?: '',
    ],
    
    // Payment settings
    'payment' => [
        'amount' => 210, // $2.10 CAD in cents (includes 5% GST)
        'currency' => 'cad',
        'description' => 'BrokeAnt Marketplace - User Registration Fee',
    ],
];
?>
