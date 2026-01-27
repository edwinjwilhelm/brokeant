<?php
// PayPal Webhook Handler - Phase 4
// Listens for PayPal payment events

header('Content-Type: application/json');
require_once '../config/database.php';

$paypal_config = require_once '../config/paypal.php';

// Verify webhook signature
$webhook_id = $paypal_config['webhook_id'];

$headers = getallheaders();
$transmission_id = $headers['PAYPAL-TRANSMISSION-ID'] ?? '';
$transmission_time = $headers['PAYPAL-TRANSMISSION-TIME'] ?? '';
$cert_url = $headers['PAYPAL-CERT-URL'] ?? '';
$auth_algo = $headers['PAYPAL-AUTH-ALGO'] ?? '';
$webhook_signature = $headers['PAYPAL-WEBHOOK-SIGNATURE'] ?? '';

$input = file_get_contents('php://input');

try {
    // Verify webhook authenticity (simplified - full verification would validate the cert)
    if (!verify_paypal_webhook($transmission_id, $transmission_time, $cert_url, $auth_algo, $webhook_signature, $input, $webhook_id)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid webhook signature']);
        return;
    }
    
    $event = json_decode($input, true);
    
    // Handle different event types
    switch ($event['event_type']) {
        case 'CHECKOUT.ORDER.COMPLETED':
            handle_order_completed($event['resource']);
            break;
            
        case 'CHECKOUT.ORDER.APPROVED':
            handle_order_approved($event['resource']);
            break;
            
        case 'PAYMENT.CAPTURE.COMPLETED':
            handle_payment_completed($event['resource']);
            break;
            
        case 'PAYMENT.CAPTURE.REFUNDED':
            handle_payment_refunded($event['resource']);
            break;
            
        default:
            // Unhandled event type
            http_response_code(200);
            echo json_encode(['received' => true]);
    }
    
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function verify_paypal_webhook($transmission_id, $transmission_time, $cert_url, $auth_algo, $webhook_signature, $webhook_body, $webhook_id) {
    global $paypal_config;
    
    // Simplified verification - in production, download and verify the certificate
    // For now, we'll just check that all required headers are present
    if (empty($transmission_id) || empty($transmission_time) || empty($webhook_signature)) {
        return false;
    }
    
    // TODO: Implement full signature verification
    // Download cert from $cert_url, verify the signature
    return true;
}

function handle_order_completed($resource) {
    global $mysqli;
    
    $order_id = $resource['id'];
    $status = $resource['status'] ?? 'COMPLETED';
    
    // This event fires when buyer approves order but before capture
    // Just log it for now
    error_log('PayPal Order Completed: ' . $order_id);
}

function handle_order_approved($resource) {
    global $mysqli;
    
    $order_id = $resource['id'];
    
    // Order was approved by buyer
    // Capture happens next
    error_log('PayPal Order Approved: ' . $order_id);
}

function handle_payment_completed($resource) {
    global $mysqli;
    
    $transaction_id = $resource['id'];
    $user_id = $resource['custom_id'] ?? null;
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

function handle_payment_refunded($resource) {
    global $mysqli;
    
    $transaction_id = $resource['links'][0]['rel'] ?? '';
    
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
