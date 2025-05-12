<?php
session_start();
header('Content-Type: application/json');

$response = [
    'is_logged_in' => false,
    'redirect_url' => null
];

if (isset($_SESSION['user_id'])) {
    $response['is_logged_in'] = true;
    $response['redirect_url'] = ($_SESSION['user_role'] === 'admin') 
        ? '/public/admin.php' 
        : '/public/profile.php';
}

echo json_encode($response);
?>