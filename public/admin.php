<?php
session_start();

// Проверка авторизации и роли admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /public/login.html");
    exit();
}

require_once __DIR__ . '/../config/db.php';

try {
    // Получаем все заявки с основной информацией
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.date,
            s.status,
            u.firstname AS user_firstname,
            u.lastname AS user_lastname,
            start_st.name AS start_station_name,
            end_st.name AS end_station_name
        FROM 
            statemants s
        JOIN 
            users u ON s.id_user = u.id
        JOIN 
            station start_st ON s.id_station_start = start_st.id
        JOIN 
            station end_st ON s.id_station_end = end_st.id
        ORDER BY 
            s.date DESC
    ");
    $stmt->execute();
    $statements = $stmt->fetchAll();
    
    // Для каждой заявки получаем список сотрудников
    foreach ($statements as &$statement) {
        $stmt = $pdo->prepare("
            SELECT 
                e.id, 
                e.name AS firstname, 
                e.surname AS lastname,
                se.is_main
            FROM 
                statement_employees se
            JOIN 
                employee e ON se.employee_id = e.id
            WHERE 
                se.statement_id = ?
            ORDER BY
                se.is_main DESC
        ");
        $stmt->execute([$statement['id']]);
        $statement['employees'] = $stmt->fetchAll();
    }
    unset($statement); // Разрываем ссылку
    
} catch (Exception $e) {
    die("Ошибка: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="source/styles/main.css">
    <title>Metro - Админ панель</title>
</head>

<body>
    <header>
        <nav class="navigation">
            <div class="d-flex justify-content-between align-items-center">
                <a href="admin.html"><img src="source/images/метроФон.png" alt="metro"></a>
                <a href="/api/logout.php" class="nav-btn">Выйти</a>
            </div>
        </nav>
    </header>
    <section class="help">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <h2>Все заявки</h2>
                    <div class="overflow-auto monitor">
                        <table class="table table-striped table-hover text-center">
                            <thead>
                                <tr>
                                    <th>Пользователь</th>
                                    <th>Сотрудники</th>
                                    <th>Начальная станция</th>
                                    <th>Конечная станция</th>
                                    <th>Дата</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($statements)): ?>
                                    <tr>
                                        <td colspan="6">Нет заявок</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($statements as $statement): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($statement['user_firstname'] . ' ' . $statement['user_lastname']) ?></td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        Сотрудники (<?= count($statement['employees']) ?>)
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php foreach ($statement['employees'] as $employee): ?>
                                                            <li>
                                                                <span class="dropdown-item">
                                                                    <?= htmlspecialchars($employee['firstname'] . ' ' . $employee['lastname']) ?>
                                                                    <?php if ($employee['is_main']): ?>
                                                                        <i class="fas fa-star text-warning ms-2" title="Основной сотрудник"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($statement['start_station_name']) ?></td>
                                            <td><?= htmlspecialchars($statement['end_station_name']) ?></td>
                                            <td><?= htmlspecialchars(date('d.m.Y', strtotime($statement['date']))) ?></td>
                                            <td>
                                                <?php 
                                                $status = htmlspecialchars($statement['status']);
                                                // Стилизация статусов
                                                $statusClass = '';
                                                if ($status === 'approved') {
                                                    $statusClass = 'text-success fw-bold';
                                                    $statusText = 'Одобрено';
                                                } elseif ($status === 'rejected') {
                                                    $statusClass = 'text-danger fw-bold';
                                                    $statusText = 'Отклонено';
                                                } elseif ($status === 'pending') {
                                                    $statusClass = 'text-primary fw-bold';
                                                    $statusText = 'В обработке';
                                                } else {
                                                    $statusText = $status;
                                                }
                                                ?>
                                                <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>
</body>

</html>