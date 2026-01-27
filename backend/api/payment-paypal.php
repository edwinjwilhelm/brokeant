<?php
// PayPal Payment Processing API - Phase 4
// Handles one-time payments for $2.10 CAD signup fee

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../middleware/auth.php';

$paypal_config = require_once '../config/paypal.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'create_order') {
        create_paypal_order();
    } elseif ($action === 'capture_order') {
        capture_paypal_order();
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function get_paypal_access_token() {
    global $paypal_config;
    
    $client_id = $paypal_config['client_id'];
    $secret_key = $paypal_config['secret_key'];
    $api_url = $paypal_config['api_url'];
    
    $auth = base64_encode($client_id . ':' . $secret_key);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to get PayPal access token: ' . $response);
    }
    
    $data = json_decode($response, true);
    return $data['access_token'];
}

function create_paypal_order() {
    global $paypal_config;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($input['user_id']);
    $amount = $paypal_config['payment']['amount'];
    $currency = $paypal_config['payment']['currency'];
    
    try {
        $access_token = get_paypal_access_token();
        $api_url = $paypal_config['api_url'];
        
        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $amount
                ],
                'description' => $paypal_config['payment']['description'],
                'custom_id' => (string)$user_id
            ]],
            'application_context' => [
                'return_url' => 'https://brokeant.com/payment-checkout.html?action=confirm',
                'cancel_url' => 'https://brokeant.com/payment-checkout.html?action=cancel',
                'brand_name' => 'BrokeAnt Marketplace',
                'locale' => 'en-CA'
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 201) {
            throw new Exception('Failed to create PayPal order: ' . $response);
        }
        
        $order = json_decode($response, true);
        
        http_response_code(201);
        echo json_encode([
            'order_id' => $order['id'],
            'links' => $order['links']
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function capture_paypal_order() {
    global $mysqli, $paypal_config;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'];
    $user_id = intval($input['user_id']);
    $amount = $paypal_config['payment']['amount'];
    
    try {
        $access_token = get_paypal_access_token();
        $api_url = $paypal_config['api_url'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url . '/v2/checkout/orders/' . $order_id . '/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 201) {
            throw new Exception('Failed to capture PayPal order: ' . $response);
        }
        
        $order = json_decode($response, true);
        
        if ($order['status'] === 'COMPLETED') {
            // Get transaction ID from capture
            $transaction_id = $order['purchase_units'][0]['payments']['captures'][0]['id'];
            $status = 'succeeded';
            
            // Record payment in database
            $stmt = $mysqli->prepare("
                INSERT INTO payments (user_id, amount, currency, gateway, transaction_id, status, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param('idsssss', $user_id, $amount, 'cad', 'paypal', $transaction_id, $status, 'paypal');
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
                'message' => 'Payment captured successfully',
                'transaction_id' => $transaction_id,
                'redirect' => '/login.html?registered=true'
            ]);
            
        } else {
            throw new Exception('PayPal order not completed: ' . $order['status']);
        }
        
    } catch (Exception $e) {
        // Record failed payment
        $status = 'failed';
        
        $stmt = $mysqli->prepare("
            INSERT INTO payments (user_id, amount, currency, gateway, transaction_id, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('idsss', $user_id, $amount, 'cad', 'paypal', $order_id, $status);
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
        echo json_encode(['error' => 'Payment failed: ' . $e->getMessage()]);
    }
}
?>
