<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/log_helpers.php';

header('Content-Type: application/json'); // Set JSON response format

// ✅ Check if user is logged in
if (!isset($_SESSION['LoggedIn'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit;
}

// ✅ Ensure `petId` is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['petId'])) {
    $petId = $_POST['petId'];
    $userId = $_SESSION['UserId'];
    $userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
    $role = $_SESSION['Role'] ?? 'Role';

    try {
        // ✅ Update pet status to confined
        $stmt = $pdo->prepare("UPDATE Pets SET IsConfined = 1, LastVisit = NOW() WHERE PetId = ?");
        $stmt->execute([$petId]);

        if ($stmt->rowCount() > 0) {
            logActivity($pdo, $userId, $userName, $role, 'confine_pet.php', "Confined Pet ID: $petId . date('Y-m-d H:i:s')");
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Failed to confine pet."]);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Invalid request."]);
}