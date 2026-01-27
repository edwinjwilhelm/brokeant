<?php
// Stripe Payment Processing API - Phase 4
// Handles one-time payments for $2.10 CAD signup fee

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../middleware/auth.php';

// Include Stripe library
require_once '../../vendor/autoload.php';
$stripe_config = require_once '../config/stripe.php';

use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Token;

// Set Stripe API key
$mode = $stripe_config['mode'];
$secret_key = $stripe_config[$mode]['secret_key'];
Stripe::setApiKey($secret_key);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'charge') {
        // Process Stripe charge
        handle_stripe_charge();
    } elseif ($action === 'get_publishable_key') {
        // Return publishable key to frontend
        get_publishable_key();
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handle_stripe_charge() {
    global $mysqli, $stripe_config;
    
    // Get data from POST
    $stripe_token = $_POST['stripe_token'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    
    // Validate required fields
    if (empty($stripe_token) || empty($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $amount = $stripe_config['payment']['amount']; // 210 cents = $2.10
    
    try {
        // Create charge
        $charge = Charge::create([
            'amount' => $amount,
            'currency' => $stripe_config['payment']['currency'],
            'source' => $stripe_token,
            'description' => $stripe_config['payment']['description'],
            'metadata' => [
                'user_id' => $user_id,
                'business' => 'BrokeAnt'
            ]
        ]);
        
        if ($charge->status === 'succeeded') {
            // Record payment in database
            $transaction_id = $charge->id;
            $status = 'succeeded';
            $payment_method = $charge->payment_method_details->type ?? 'card';
            
            $stmt = $mysqli->prepare("
                INSERT INTO payments (user_id, amount, currency, gateway, transaction_id, status, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param('idsssss', $user_id, $amount, 'cad', 'stripe', $transaction_id, $status, $payment_method);
            $stmt->execute();
            
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
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Payment successful',
                'transaction_id' => $transaction_id,
                'redirect' => '/login.html?registered=true'
            ]);
            
        } else {
            throw new Exception('Charge status: ' . $charge->status);
        }
        
    } catch (Exception $e) {
        // Record failed payment
        $transaction_id = $input['stripe_token'];
        $status = 'failed';
        
        $stmt = $mysqli->prepare("
            INSERT INTO payments (user_id, amount, currency, gateway, transaction_id, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('idsss', $user_id, $amount, 'cad', 'stripe', $transaction_id, $status);
        $stmt->execute();
        
        // Update user payment status
        $stmt = $mysqli->prepare("
            UPDATE users 
            SET payment_status = 'failed'
            WHERE id = ?
        ");
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        http_response_code(400);
        echo json_encode([
            'error' => 'Payment failed: ' . $e->getMessage()
        ]);
    }
}

function get_publishable_key() {
    global $stripe_config;
    
    $mode = $stripe_config['mode'];
    $publishable_key = $stripe_config[$mode]['publishable_key'];
    
    http_response_code(200);
    echo json_encode([
        'publishable_key' => $publishable_key
    ]);
}
?>
