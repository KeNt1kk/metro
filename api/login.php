<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

session_start();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT id, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            
            $response = [
                'success' => true,
                'message' => 'Вход выполнен успешно!',
                'redirect' => ($user['role'] === 'admin') ? '/public/admin.html' : '/public/profile.php'
            ];
        } else {
            $response['message'] = 'Неверный email или пароль';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Ошибка базы данных: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>