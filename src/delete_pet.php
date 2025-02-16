<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
session_start();
require_once __DIR__ . '/../config/dbh.inc.php';

error_log("🛠 Delete Pet Script Called");

// Ensure the request is **POST**
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("❌ Invalid request method.");
    echo json_encode(["success" => false, "error" => "Invalid request method."]);
    exit;
}

// Decode JSON request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['pet_id']) || !is_numeric($data['pet_id'])) {
    error_log("❌ Invalid Pet ID received: " . json_encode($data));
    echo json_encode(["success" => false, "error" => "Invalid Pet ID."]);
    exit;
}

$petId = (int)$data['pet_id'];
error_log("✅ Processing deletion for Pet ID: " . $petId);

try {
    $query = "SELECT PetId FROM Pets WHERE PetId = ? AND IsArchived = 0";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$petId]);

    if ($stmt->rowCount() === 0) {
        error_log("❌ Pet not found or already archived: " . $petId);
        echo json_encode(["success" => false, "error" => "Pet not found or already deleted."]);
        exit;
    }

    // Perform the soft delete
    $deleteQuery = "UPDATE Pets SET IsArchived = 1 WHERE PetId = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$petId]);

    if ($deleteStmt->rowCount() > 0) {
        error_log("✅ Pet record archived successfully.");
        echo json_encode(["success" => true, "message" => "Pet deleted successfully."]);
    } else {
        error_log("❌ Failed to delete pet record.");
        echo json_encode(["success" => false, "error" => "Failed to delete the pet record."]);
    }
} catch (PDOException $e) {
    error_log("❌ Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error."]);
}
exit;