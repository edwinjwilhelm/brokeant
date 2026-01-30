<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.html');
        exit;
    }
}

function require_payment() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.html');
        exit;
    }
    
    if (($_SESSION['payment_status'] ?? null) !== 'verified') {
        header('Location: /payment-checkout.html');
        exit;
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_payment_verified() {
    return isset($_SESSION['user_id']) && ($_SESSION['payment_status'] ?? null) === 'verified';
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_name() {
    return $_SESSION['user_name'] ?? null;
}

function get_user_city() {
    return $_SESSION['user_city'] ?? null;
}

function get_payment_status() {
    return $_SESSION['payment_status'] ?? null;
}
?>
