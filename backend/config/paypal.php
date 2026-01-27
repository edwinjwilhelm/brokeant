<?php
// PayPal Configuration - Phase 4
// REST API credentials for live transactions

return [
    'mode' => 'live', // PayPal live mode (production)
    
    'client_id' => 'AeWy0PVnwQL4ZrxTrcLcoZCSasIf0EellyWQ_rVT66WH8apdM-0ZkVHp5ksIOZ30Qrl8iAEF1l9DlCG5',
    
    // TODO: Add your PayPal Secret key here
    // Run: grep "Secret key" and add it below
    'secret_key' => '', // ASK USER TO PROVIDE
    
    'webhook_id' => '4WN31555TB3374303',
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
