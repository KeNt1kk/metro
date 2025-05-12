<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

session_start();

$response = ['success' => false, 'message' => '', 'retry' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем и валидируем данные
    $startStation = trim($_POST['startStation'] ?? '');
    $endStation = trim($_POST['endStation'] ?? '');
    $baggageAvailability = (int)$_POST['baggageAvailability'];
    $dateStatemant = $_POST['dateStatemant'] ?? date('Y-m-d');
    $userId = $_SESSION['user_id'] ?? null;

    // Проверяем обязательные поля
    if (empty($startStation) || empty($endStation) || empty($userId)) {
        $response['message'] = 'Заполните все обязательные поля.';
        echo json_encode($response);
        exit();
    }

    try {
        // Получаем информацию о пользователе
        $userStmt = $pdo->prepare("SELECT mobility FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $response['message'] = 'Пользователь не найден.';
            echo json_encode($response);
            exit();
        }
        
        $mobility = $user['mobility'];
        
        // Проверяем станции
        $stationStmt = $pdo->prepare("SELECT id, `line` FROM station WHERE name = ? LIMIT 1");
        $stationStmt->execute([$startStation]);
        $startStationData = $stationStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$startStationData) {
            $response['message'] = 'Станция отправления не найдена.';
            echo json_encode($response);
            exit();
        }
        
        $stationStmt->execute([$endStation]);
        $endStationData = $stationStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$endStationData) {
            $response['message'] = 'Станция назначения не найдена.';
            echo json_encode($response);
            exit();
        }
        
        $startStationId = $startStationData['id'];
        $startStationLine = $startStationData['line'];
        $endStationId = $endStationData['id'];
        
        // Определяем необходимое количество сотрудников
        $requiredEmployees = calculateRequiredEmployees($mobility, $baggageAvailability);
        
        // Находим свободных сотрудников
        $employees = findAvailableEmployees($pdo, $startStationId, $startStationLine, $dateStatemant, $requiredEmployees);
        if (empty($employees)) {
            // Если ни один сотрудник не найден
            $response = [
                'success' => false,
                'empty' => true,
                'message' => 'Свободных сотрудников на этот день не найдено. Вы можете повторить попытку позже',
                'retry' => true
            ];
            echo json_encode($response);
            exit; 
        }
        
        // Всегда создаем заявку, даже если сотрудников не хватает
        $result = createStatement(
            $pdo, 
            $baggageAvailability, 
            $dateStatemant, 
            $startStationId, 
            $endStationId, 
            $userId, 
            $employees,
            $requiredEmployees
        );
        
        if ($result['status'] === 'approved') {
            $response['success'] = true;
            $response['message'] = 'Заявка одобрена. Назначено сотрудников: ' . $result['employees_found'];
        } else {
            $response['success'] = false;
            $response['message'] = 'Заявка создана, но ожидает назначения сотрудников (найдено ' . 
                                $result['employees_found'] . ' из ' . $result['employees_required'] . 
                                '). Мы уведомим вас, когда сотрудники будут назначены.';
            $response['retry'] = true;
        }
    } catch (Exception $e) {
        $response['message'] = 'Ошибка при создании заявки: ' . $e->getMessage();
    }
}

echo json_encode($response);

/**
 * Расчет необходимого количества сотрудников
 */
function calculateRequiredEmployees($mobility, $baggageAvailability) {
    $required = 1; // минимальное количество
    
    if ($mobility === 'walking') {
        $required = 2; // базовое количество для колясочника
        if ($baggageAvailability) {
            $required = 4; // максимум для колясочника с багажом
        }
    } elseif ($baggageAvailability) {
        $required = 2; // для слабовидящего с багажом
    }

    return $required;
}

/**
 * Поиск свободных сотрудников (сначала на станции, потом на линии)
 */
function findAvailableEmployees($pdo, $stationId, $line, $date, $requiredCount) {
    $employees = [];

    // 1. Ищем на станции
    $query = "SELECT e.id 
              FROM employee e
              WHERE e.id_station = ?
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
                        AND e.id NOT IN (" . ($employees ? implode(',', array_map('intval', $employees)) : 'NULL') . ")
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
/**
 * Создание заявки и назначение сотрудников
 */
function createStatement($pdo, $baggage, $date, $startStationId, $endStationId, $userId, $employees, $requiredCount) {
    $pdo->beginTransaction();
    
    try {
        // Определяем статус
        $status = (count($employees) >= $requiredCount) ? 'approved' : 'pending';
        $mainEmployeeId = !empty($employees) ? $employees[0] : null;
        
        // Создаем заявку
        $stmt = $pdo->prepare("
            INSERT INTO statemants 
            (bagage, date, status, id_station_start, id_station_end, id_user, id_employee) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $baggage ? 1 : 0,
            $date,
            $status,
            $startStationId,
            $endStationId,
            $userId,
            $mainEmployeeId
        ]);
        $statementId = $pdo->lastInsertId();
        
        // Добавляем сотрудников и обновляем их счетчики
        if (!empty($employees)) {
            $stmt = $pdo->prepare("
                INSERT INTO statement_employees 
                (statement_id, employee_id, is_main) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($employees as $index => $employeeId) {
                $isMain = ($index === 0) ? 1 : 0;
                $stmt->execute([$statementId, $employeeId, $isMain]);
                
                // Обновляем счетчик заявок сотрудника
                updateEmployeeStatementsCount($pdo, $employeeId, $date);
            }
        }
        
        $pdo->commit();
        return [
            'id' => $statementId,
            'status' => $status,
            'employees_found' => count($employees),
            'employees_required' => $requiredCount
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
/**
 * Обновляет счетчик заявок сотрудника в таблице employment
 */
function updateEmployeeStatementsCount($pdo, $employeeId, $date) {
    // Сначала проверяем существование записи
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