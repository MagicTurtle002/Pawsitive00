<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['serviceId'])) {
        echo json_encode(["success" => false, "message" => "Missing service ID."]);
        exit();
    }

    $serviceId = intval($data['serviceId']);

    try {
        $stmt = $pdo->prepare("DELETE FROM Services WHERE ServiceId = ?");
        $stmt->execute([$serviceId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Service not found."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
}
?>