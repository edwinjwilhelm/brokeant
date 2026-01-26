<?php
$host = 'localhost';
$user = 'brokeant_user';
$pass = 'MyBrokeAnt123';
$db = 'brokeant';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");
?>
