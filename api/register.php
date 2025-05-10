<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $name = trim($_POST['name'] ?? '');    
    $surname = trim($_POST['surname'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeatPassword = $_POST['repeatPassword'] ?? '';
    $mobility = $_POST['mobility'] ?? '';

    // Валидация данных
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Некорректный email';
        echo json_encode($response);
        exit;
    }

    if (strlen($password) < 6) {
        $response['message'] = 'Пароль должен содержать минимум 6 символов';
        echo json_encode($response);
        exit;
    }

    if ($password !== $repeatPassword) {
        $response['message'] = 'Пароли не совпадают';
        echo json_encode($response);
        exit;
    }
    //2-20 символов, только буквы и дефисы
    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z-]{2,20}$/u', $name)) {
        $response['message'] = 'Имя должно содержать 2-20 буквенных символов';
        echo json_encode($response);
        exit;
    }

    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z-]{2,20}$/u', $surname)) {
        $response['message'] = 'Фамилия должна содержать 2-20 буквенных символов';
        echo json_encode($response);
        exit;
    }
    if (strlen($mobility) < 1) {
        $response['message'] = 'Выберите тип мобильности';
        echo json_encode($response);
        exit;
    }
    try {
        // Проверяем, существует ли пользователь с таким email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $response['message'] = 'Пользователь с таким email уже существует';
            echo json_encode($response);
            exit;
        }

        // Хешируем пароль
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Добавляем нового пользователя
        $stmt = $pdo->prepare("INSERT INTO users (email, password, firstname, lastname, mobility) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$email, $hashedPassword, $name, $surname, $mobility]);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $response['success'] = true;
        $response['message'] = 'Регистрация прошла успешно!';
    } catch (PDOException $e) {
        $response['message'] = 'Ошибка базы данных: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>