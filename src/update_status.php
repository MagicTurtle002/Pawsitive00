<?php
require __DIR__ . '/../config/dbh.inc.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = filter_input(INPUT_POST, 'appointmentId', FILTER_VALIDATE_INT);
    $newStatus = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($appointmentId && in_array($newStatus, ['Pending', 'Confirmed', 'Done'])) {
        try {
            $query = "UPDATE Appointments SET Status = :status WHERE AppointmentId = :appointmentId";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':status' => $newStatus,
                ':appointmentId' => $appointmentId,
            ]);

            echo json_encode(["success" => true]);
            exit;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
        }
    }
}

echo json_encode(["success" => false]);
exit;