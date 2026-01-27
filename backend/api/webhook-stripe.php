<?php
// Stripe Webhook Handler - Phase 4
// Listens for Stripe payment events

header('Content-Type: application/json');
require_once '../config/database.php';

require_once '../../vendor/autoload.php';
$stripe_config = require_once '../config/stripe.php';

use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

// Get webhook secret
$mode = $stripe_config['mode'];
$webhook_secret = $stripe_config[$mode]['webhook_secret'];

// Get Stripe signature from headers
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Get raw request body
$input = file_get_contents('php://input');

try {
    // Verify webhook signature
    $event = Webhook::constructEvent($input, $sig_header, $webhook_secret);
    
    // Handle different event types
    switch ($event->type) {
        case 'charge.succeeded':
            handle_charge_succeeded($event->data->object);
            break;
            
        case 'charge.failed':
            handle_charge_failed($event->data->object);
            break;
            
        case 'charge.refunded':
            handle_charge_refunded($event->data->object);
            break;
            
        default:
            // Unhandled event type
            http_response_code(200);
            echo json_encode(['received' => true]);
    }
    
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function handle_charge_succeeded($charge) {
    global $mysqli;
    
    $transaction_id = $charge->id;
    $user_id = $charge->metadata->user_id ?? null;
    $status = 'succeeded';
    
    if (!$user_id) {
        return;
    }
    
    // Update user payment status
    $payment_date = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("
        UPDATE users 
        SET payment_status = 'verified', 
            payment_date = ?, 
            payment_transaction_id = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param('ssi', $payment_date, $transaction_id, $user_id);
    $stmt->execute();
}

function handle_charge_failed($charge) {
    global $mysqli;
    
    $transaction_id = $charge->id;
    $user_id = $charge->metadata->user_id ?? null;
    
    if (!$user_id) {
        return;
    }
    
    // Update user payment status
    $stmt = $mysqli->prepare("
        UPDATE users 
        SET payment_status = 'failed'
        WHERE id = ?
    ");
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}

function handle_charge_refunded($charge) {
    global $mysqli;
    
    $transaction_id = $charge->id;
    
    // Update payment status to refunded
    $stmt = $mysqli->prepare("
        UPDATE payments 
        SET status = 'refunded'
        WHERE transaction_id = ?
    ");
    
    $stmt->bind_param('s', $transaction_id);
    $stmt->execute();
}
?>
