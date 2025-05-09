<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

session_start();

$response = ['success' => false, 'message' => '', 'retry' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем и валидируем данные
    $startStation = trim($_POST['startStation'] ?? '');
    $endStation = trim($_POST['endStation'] ?? '');
    $baggageAvailability = isset($_POST['baggageAvailability']) ? 1 : 0;
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
   
        if (count($employees) >= $requiredEmployees) {
            // Создаем заявку
            $statementId = createStatement(
                $pdo, 
                $baggageAvailability, 
                $dateStatemant, 
                $startStationId, 
                $endStationId, 
                $userId, 
                $employees,
                $requiredEmployees
            );
            
            $response['success'] = true;
            $response['message'] = 'Заявка создана. Назначено сотрудников: ' . count($employees);
        } else {
            $response['message'] = 'Недостаточно свободных сотрудников. Попробуйте позже.';
            $response['retry'] = true;
            $response['retry_after'] = 300;
        }
    } catch (PDOException $e) {
        // Выводим общую информацию об ошибке
        echo "Ошибка в SQL запросе:\n";
        echo "Сообщение: " . $e->getMessage() . "\n";
        echo "Код ошибки: " . $e->getCode() . "\n";
        
        // Если ошибка в подготовленном запросе, выводим его
        if (isset($stmt)) {
            echo "Запрос: " . $stmt->queryString . "\n";
            echo "Параметры: " . print_r($stmt->errorInfo(), true) . "\n";
        } elseif (isset($userStmt)) {
            echo "Запрос (users): " . $userStmt->queryString . "\n";
        } elseif (isset($stationStmt)) {
            echo "Запрос (station): " . $stationStmt->queryString . "\n";
        }
        
        // Выводим стек вызовов для отладки
        echo "Стек вызовов:\n";
        print_r($e->getTrace());
        die();
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
    AND e.id IN (
        SELECT em.id_employee 
        FROM employment em
        WHERE em.date = ?
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
                    AND e.id IN (
                        SELECT em.id_employee 
                        FROM employment em
                        WHERE em.date = ?
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
        // Основной сотрудник (первый в списке)
        $mainEmployeeId = $employees[0];
        
        // Создаем заявку
        $stmt = $pdo->prepare("
            INSERT INTO statemants 
            (bagage, date, status, id_station_start, id_station_end, id_user, id_employee) 
            VALUES (?, ?, 'pending', ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $baggage ? 'yes' : 'no',
            $date,
            $startStationId,
            $endStationId,
            $userId,
            $mainEmployeeId
        ]);
        
        $statementId = $pdo->lastInsertId();
               
        $pdo->commit();
        return $statementId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>