<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['serviceName']) || !isset($data['servicePrice'])) {
        echo json_encode(["success" => false, "message" => "Missing service name or price."]);
        exit();
    }

    $serviceName = trim($data['serviceName']);
    $servicePrice = $data['servicePrice'] !== '' ? floatval($data['servicePrice']) : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO Services (ServiceName, Price) VALUES (?, ?)");
        $stmt->execute([$serviceName, $servicePrice]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Service added successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to add service."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
}
?>