<?php
// PayPal Configuration - Phase 4
// API credentials are stored in environment variables for security

return [
    'mode' => 'live', // PayPal live mode (production)
    
    'client_id' => getenv('PAYPAL_CLIENT_ID') ?: '',
    'secret_key' => getenv('PAYPAL_SECRET_KEY') ?: '',
    
    'webhook_id' => getenv('PAYPAL_WEBHOOK_ID') ?: '',
    'webhook_url' => 'https://brokeant.com/backend/api/webhook-paypal.php',
    
    // API endpoints
    'api_url' => 'https://api.paypal.com', // Live URL
    'sandbox_url' => 'https://api.sandbox.paypal.com', // Sandbox for testing
    
    // Payment settings
    'payment' => [
        'amount' => '2.10', // $2.10 CAD (includes 5% GST)
        'currency' => 'CAD',
        'description' => 'BrokeAnt Marketplace - User Registration Fee',
    ],
];
?>
