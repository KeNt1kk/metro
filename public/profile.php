<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

try {
    // Получаем данные пользователя из БД
    $stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        throw new Exception("Пользователь не найден");
    }
    
    // Получаем заявки пользователя с дополнительной информацией
    $stmt = $pdo->prepare("
        SELECT 
            s.date, 
            s.status,
            start_st.name AS start_station_name,
            end_st.name AS end_station_name
        FROM 
            statemants s
        JOIN 
            station start_st ON s.id_station_start = start_st.id
        JOIN 
            station end_st ON s.id_station_end = end_st.id
        WHERE 
            s.id_user = ? 
        ORDER BY 
            s.date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $statements = $stmt->fetchAll();
    
} catch (Exception $e) {
    // Обработка ошибок
    die("Ошибка: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="source/styles/main.css">
    <title>Metro</title>
</head>

<body>
    <header>
        <nav class="navigation">
            <div class="d-flex justify-content-between align-items-center">
                <a href="main.html"><img src="source/images/метроФон.png" alt="metro"></a>
                <p class="name"><?php echo htmlspecialchars($userData['firstname'].' '.htmlspecialchars($userData['lastname'])); ?></p>
            </div>
        </nav>
    </header>
    <section class="help">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-2"></div>
                <div class="col-lg-8">
                    <p>Поданные заявки</p>
                    <div class="overflow-auto employee">
                        <table class="table table-striped table-hover text-center">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Станция отправления</th>
                                    <th>Станция назначения</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($statements)): ?>
                                    <tr>
                                        <td colspan="4">Нет поданных заявок</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($statements as $statement): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($statement['date']))); ?></td>
                                            <td><?php echo htmlspecialchars($statement['start_station_name']); ?></td>
                                            <td><?php echo htmlspecialchars($statement['end_station_name']); ?></td>
                                            <td>
                                                <?php 
                                                $status = htmlspecialchars($statement['status']);
                                                // Можно добавить перевод статусов или стилизацию
                                                echo $status === 'pending' ? 'В обработке' : 
                                                     ($status === 'completed' ? 'Выполнено' : $status);
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-2 d-flex align-items-end help">
                    <a href="main.html" class="apply-btn"><span class="plus">⟵</span>Обратно</a>
                </div>
            </div>
        </div>
    </section>
</body>

</html>