<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Требуется авторизация';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Недопустимый метод запроса';
    echo json_encode($response);
    exit();
}

// Получаем ID заявки
$statementId = $_POST['statement_id'] ?? null;
if (empty($statementId)) {
    $response['message'] = 'Не указан ID заявки';
    echo json_encode($response);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // 1. Получаем информацию о заявке и текущих сотрудниках
    $stmt = $pdo->prepare("
        SELECT 
            s.id, s.status, s.id_station_start, s.id_station_end, s.date, 
            s.bagage, u.mobility, u.id as user_id,
            start_st.line as start_line,
            GROUP_CONCAT(se.employee_id) as current_employees
        FROM statemants s
        JOIN users u ON s.id_user = u.id
        JOIN station start_st ON s.id_station_start = start_st.id
        LEFT JOIN statement_employees se ON s.id = se.statement_id
        WHERE s.id = ? AND s.id_user = ?
        GROUP BY s.id
    ");
    $stmt->execute([$statementId, $_SESSION['user_id']]);
    $statement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$statement) {
        $response['message'] = 'Заявка не найдена или у вас нет прав на ее обновление';
        echo json_encode($response);
        exit();
    }
    
    // Проверяем, что заявка в ожидании
    if ($statement['status'] !== 'pending') {
        $response['message'] = 'Можно обновлять только заявки со статусом "В обработке"';
        echo json_encode($response);
        exit();
    }
    
    // 2. Получаем текущих сотрудников
    $currentEmployees = [];
    if (!empty($statement['current_employees'])) {
        $currentEmployees = explode(',', $statement['current_employees']);
    }
    
    // 3. Определяем необходимое количество сотрудников
    $baggageAvailability = ($statement['bagage'] === 'yes') ? 1 : 0;
    $mobility = $statement['mobility'];
    $requiredEmployees = calculateRequiredEmployees($mobility, $baggageAvailability);
    
    // 4. Ищем новых сотрудников (исключая уже назначенных)
    $employees = findAvailableEmployees(
        $pdo, 
        $statement['id_station_start'], 
        $statement['start_line'], 
        $statement['date'], 
        $requiredEmployees - count($currentEmployees), // Ищем только недостающих
        $currentEmployees // Исключаем уже назначенных
    );
    
    // 5. Объединяем текущих и новых сотрудников
    $allEmployees = array_merge($currentEmployees, $employees);
    $mainEmployeeId = !empty($allEmployees) ? $allEmployees[0] : null;
    
    // 6. Обновляем статус заявки
    $newStatus = (count($allEmployees) >= $requiredEmployees) ? 'approved' : 'pending';
    
    $updateStmt = $pdo->prepare("
        UPDATE statemants 
        SET status = ?, id_employee = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$newStatus, $mainEmployeeId, $statementId]);
    
    // 7. Добавляем только новых сотрудников
    if (!empty($employees)) {
        $stmt = $pdo->prepare("
            INSERT INTO statement_employees 
            (statement_id, employee_id, is_main) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($employees as $index => $employeeId) {
            $isMain = 0; // Основным остается первый из текущих сотрудников
            $stmt->execute([$statementId, $employeeId, $isMain]);
            updateEmployeeStatementsCount($pdo, $employeeId, $statement['date']);
        }
    }
    
    $pdo->commit();
    
    $response['success'] = true;
    $response['status'] = $newStatus;
    $response['employees_found'] = count($allEmployees);
    $response['employees_required'] = $requiredEmployees;
    $response['current_employees'] = count($currentEmployees);
    $response['new_employees'] = count($employees);
    
    if ($newStatus === 'approved') {
        $response['message'] = 'Заявка одобрена. Назначено сотрудников: ' . count($allEmployees);
    } else {
        $response['message'] = 'Заявка обновлена. Текущее количество сотрудников: ' . 
                             count($allEmployees) . ' из ' . $requiredEmployees;
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    $response['message'] = 'Ошибка при обновлении заявки: ' . $e->getMessage();
}

echo json_encode($response);

// Модифицированная функция для поиска сотрудников с исключением уже назначенных
function findAvailableEmployees($pdo, $stationId, $line, $date, $requiredCount, $excludeEmployees = []) {
    $employees = [];
    
    if ($requiredCount <= 0) {
        return $employees;
    }

    $excludeCondition = '';
    if (!empty($excludeEmployees)) {
        $excludeCondition = 'AND e.id NOT IN (' . implode(',', array_map('intval', $excludeEmployees)) . ')';
    }

    // 1. Ищем на станции
    $query = "SELECT e.id 
              FROM employee e
              WHERE e.id_station = ?
                $excludeCondition
                AND EXISTS (
                    SELECT 1
                    FROM employment em
                    WHERE em.id_employee = e.id
                      AND em.date = ?
                      AND em.current_statemants < 5
                )
              LIMIT " . (int)$requiredCount;

    $stmt = $pdo->prepare($query);
    $stmt->execute([$stationId, $date]);
    $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Если не хватает, ищем на линии
    if (count($employees) < $requiredCount) {
        $remaining = $requiredCount - count($employees);

        $lineQuery = "SELECT e.id 
                      FROM employee e
                      JOIN station s ON e.id_station = s.id
                      WHERE s.`line` = ?
                        AND e.id_station != ?
                        $excludeCondition
                        AND EXISTS (
                            SELECT 1
                            FROM employment em
                            WHERE em.id_employee = e.id
                              AND em.date = ?
                              AND em.current_statemants < 5
                        )
                      LIMIT " . (int)$remaining;

        $lineStmt = $pdo->prepare($lineQuery);
        $lineStmt->execute([$line, $stationId, $date]);
        $lineEmployees = $lineStmt->fetchAll(PDO::FETCH_COLUMN);

        $employees = array_merge($employees, $lineEmployees);
    }

    return $employees;
}

// Остальные функции остаются без изменений
function calculateRequiredEmployees($mobility, $baggageAvailability) {
    $required = 1;
    
    if ($mobility === 'walking') {
        $required = 2;
        if ($baggageAvailability) {
            $required = 4;
        }
    } elseif ($baggageAvailability) {
        $required = 2;
    }

    return $required;
}

function updateEmployeeStatementsCount($pdo, $employeeId, $date) {
    $checkStmt = $pdo->prepare("
        SELECT id FROM employment 
        WHERE id_employee = ? AND date = ?
    ");
    $checkStmt->execute([$employeeId, $date]);
    $exists = $checkStmt->fetch();
    
    if ($exists) {
        $updateStmt = $pdo->prepare("
            UPDATE employment 
            SET current_statemants = current_statemants + 1 
            WHERE id_employee = ? AND date = ?
        ");
        $updateStmt->execute([$employeeId, $date]);
    }
}
?>