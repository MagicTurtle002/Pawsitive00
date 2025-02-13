<?php
require '../config/dbh.inc.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['serviceId']) || !isset($data['newServiceName'])) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

$serviceId = (int) $data['serviceId'];
$newServiceName = trim($data['newServiceName']);
$newPrice = isset($data['newPrice']) ? (float) $data['newPrice'] : null;

try {
    $query = "UPDATE Services SET ServiceName = ?, Price = ? WHERE ServiceId = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$newServiceName, $newPrice, $serviceId]);

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>