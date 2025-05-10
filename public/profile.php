<?php
session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: /public/login.html");
    exit();
}

require_once __DIR__ . '/../config/db.php';

try {
    // Получаем данные пользователя из БД
    $stmt = $pdo->prepare("SELECT firstname, lastname, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        throw new Exception("Пользователь не найден");
    }
    
    // Получаем заявки пользователя с основной информацией
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
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
    <title>Metro</title>
</head>

<body>
    <header>
        <nav class="navigation">
            <div class="d-flex justify-content-between align-items-center">
                <a href="main.html"><img src="source/images/метроФон.png" alt="metro"></a>
                <a href="/api/logout.php" class="nav-btn">Выйти</a>
                <p class="name"><?php echo htmlspecialchars($userData['firstname'].' '.$userData['lastname']); ?></p>
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
                                    <th>Сотрудники</th>
                                    <th>Статус</th>
                                    <th>Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($statements)): ?>
                                    <tr>
                                        <td colspan="5">Нет поданных заявок</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($statements as $statement): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($statement['date']))); ?></td>
                                            <td><?php echo htmlspecialchars($statement['start_station_name']); ?></td>
                                            <td><?php echo htmlspecialchars($statement['end_station_name']); ?></td>
                                            <td>
                                                <?php if (!empty($statement['employees'])): ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                            Показать сотрудников (<?= count($statement['employees']) ?>)
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
                                                <?php else: ?>
                                                    Нет назначенных
                                                <?php endif; ?>
                                            </td>
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
                                                } elseif ($status === 'completed') {
                                                    $statusClass = 'text-success fw-bold';
                                                    $statusText = 'Выполнено';
                                                }else {
                                                    $statusText = $status;
                                                }
                                                ?>
                                                <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <?php if ($status === 'pending'): ?>
                                                    <form id="updateForm" class="d-inline">
                                                        <input type="hidden" id="statement_id" name="statement_id" value="<?= $statement['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-sync-alt"></i> Обновить
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-2 help position-relative" style="min-height: 300px;">
                    <div class="position-absolute bottom-0 end-0">
                        <div class="d-flex flex-column align-items-end gap-2">
                            <a href="main.php" class="apply-btn"><span class="plus">⟵</span>Обратно</a>
                            <?php if ($userData['role'] === 'admin'): ?>
                                <a href="admin.php" class="apply-btn admin-btn"><span class="plus">⚙️</span>Админ панель</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script src="scripts/update_statemant.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>
</body>

</html>