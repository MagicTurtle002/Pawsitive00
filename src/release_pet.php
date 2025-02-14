<?php
require __DIR__ . '/../config/dbh.inc.php';
session_start();

header('Content-Type: application/json');

// ✅ Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid request method."]);
    exit;
}

// ✅ Check if the petId is provided
if (empty($_POST['petId'])) {
    echo json_encode(["error" => "Pet ID is required."]);
    exit;
}

$petId = (int) $_POST['petId'];

try {
    // ✅ Update the IsConfined column to 0 in the database
    $stmt = $pdo->prepare("UPDATE Pets SET IsConfined = 0 WHERE PetId = ?");
    $stmt->execute([$petId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to release pet."]);
    }
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
exit;