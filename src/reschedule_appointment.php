<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/helpers/auth_helpers.php';
require __DIR__ . '/helpers/session_helpers.php';
require __DIR__ . '/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

function respondWithJson($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWithJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if ($data === null) {
    respondWithJson(['success' => false, 'message' => 'Invalid JSON format.'], 400);
}

$appointmentId = $data['appointment_id'] ?? null;
$newDate = $data['new_date'] ?? null;
$newTime = $data['new_time'] ?? null;

if (!$appointmentId || !$newDate || !$newTime) {
    respondWithJson(['success' => false, 'message' => 'Missing required fields.'], 400);
}

try {
    $stmt = $pdo->prepare("
        UPDATE Appointments
        SET AppointmentDate = :newDate, AppointmentTime = :newTime
        WHERE AppointmentId = :appointmentId
    ");
    $stmt->execute([
        ':newDate' => $newDate,
        ':newTime' => $newTime,
        ':appointmentId' => $appointmentId
    ]);

    if ($stmt->rowCount()) {
        respondWithJson(['success' => true, 'message' => '✅ Appointment rescheduled successfully.']);
    } else {
        respondWithJson(['success' => false, 'message' => '⚠️ No changes made.']);
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    respondWithJson(['success' => false, 'message' => '❌ Database error occurred.']);
}
?>