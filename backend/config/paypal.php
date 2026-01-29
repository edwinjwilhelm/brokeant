<?php
// PayPal Configuration - Phase 4
// Load environment variables first
require_once __DIR__ . '/.env.loader.php';

// API credentials are stored in environment variables for security

return [
    'mode' => 'live', // PayPal live mode (production)

    // Prefer explicit live env names, fallback to legacy names if present
    'client_id' => getenv('PAYPAL_LIVE_CLIENT_ID') ?: (getenv('PAYPAL_CLIENT_ID') ?: ''),
    'secret_key' => getenv('PAYPAL_LIVE_SECRET') ?: (getenv('PAYPAL_SECRET_KEY') ?: ''),

    'webhook_id' => getenv('PAYPAL_WEBHOOK_ID') ?: '',
    'webhook_url' => 'https://www.brokeant.com/backend/api/webhook-paypal.php',

    // API endpoints
    'api_url' => 'https://api-m.paypal.com', // Live URL
    'sandbox_url' => 'https://api-m.sandbox.paypal.com', // Sandbox for testing
    
    // Payment settings
    'payment' => [
        'amount' => '2.10', // $2.10 CAD (includes 5% GST)
        'currency' => 'CAD',
        'description' => 'BrokeAnt Marketplace - User Registration Fee',
    ],
];
?>
