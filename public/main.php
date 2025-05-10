<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: /public/login.html");
    exit();
}

require_once __DIR__ . '/../config/db.php';

try {
    // Получаем свободных сотрудников (у которых текущих заявок < 5)
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.name AS firstname,
            e.surname AS lastname,
            s.name AS station_name,
            s.line AS line_name,
            em.date AS employee_date,
            COUNT(em.id) AS current_statements
        FROM 
            employee e
        JOIN 
            station s ON e.id_station = s.id
        LEFT JOIN 
            employment em ON e.id = em.id
        GROUP BY 
            e.id
        HAVING 
            current_statements < 5
        ORDER BY 
            current_statements ASC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();
    
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
    <link rel="stylesheet" href="source/styles/main.css">
    <title>Metro - Свободные сотрудники</title>
</head>

<body>
    <header>
        <nav class="navigation">
            <div class="d-flex justify-content-between align-items-center">
                <a href="main.php"><img src="source/images/метроФон.png" alt="metro"></a>
                <a href="profile.php" class="nav-btn">Профиль</a>
            </div>
        </nav>
    </header>
    <section>
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-2"></div>
                <div class="col-lg-8">
                    <p>Свободные сотрудники</p>
                    <div class="overflow-auto employee">
                        <table class="table table-striped table-hover text-center">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Сотрудник</th>
                                    <th>Станция</th>
                                    <th>Линия</th>
                                    <th>Текущие заявки</th>
                                    <th>Дата</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?= htmlspecialchars($employee['id']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($employee['firstname'] . ' ' . htmlspecialchars($employee['lastname'])) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($employee['station_name']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($employee['line_name']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($employee['current_statements']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($employee['employee_date']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-2 d-flex align-items-end help">
                    <button onclick="window.location.href = '/public/statemant.html';" class="apply-btn"><span class="plus">+</span>Заявка</button>
                </div>
            </div>
        </div>
    </section>
</body>

</html>