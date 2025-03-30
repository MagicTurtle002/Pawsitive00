<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['appointment_id'], $data['pet_id'], $data['weight'], $data['temperature'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$appointmentId = (int)$data['appointment_id'];
$petId = (int)$data['pet_id'];
$weight = (float)$data['weight'];
$temperature = (float)$data['temperature'];
$override = isset($data['override']) ? (bool)$data['override'] : false;

// ✅ Check for unusual temperature (outside 30°C - 45°C)
$isUnusualTemp = ($temperature < 30 || $temperature > 45);

if ($weight <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid weight.']);
    exit;
}

// ✅ If temperature is unusual and override is NOT set, ask for confirmation
if ($isUnusualTemp && !$override) {
    echo json_encode([
        'success' => false,
        'warning' => true,
        'message' => "The temperature entered ({$temperature}°C) is unusual. Do you want to proceed?",
        'temperature' => $temperature
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // ✅ Check if the pet's vitals were already recorded today
    $stmtCheck = $pdo->prepare("SELECT 1 FROM PetWeights WHERE PetId = :pet_id AND DATE(RecordedAt) = CURDATE() LIMIT 1");
    $stmtCheck->execute([':pet_id' => $petId]);
    $alreadyRecorded = $stmtCheck->fetchColumn();

    if ($alreadyRecorded) {
        echo json_encode(['success' => false, 'alreadyRecorded' => true, 'message' => 'Vitals already recorded for today.']);
        $pdo->rollBack();
        exit;
    }

    // ✅ Insert weight entry
    $stmtWeight = $pdo->prepare("INSERT INTO PetWeights (PetId, Weight, Temperature, RecordedAt) VALUES (:pet_id, :weight, :temperature, NOW())");
    $stmtWeight->execute([':pet_id' => $petId, ':weight' => $weight, ':temperature' => $temperature]);

    // ✅ Update Pets table with latest weight and temperature
    $stmtPet = $pdo->prepare("UPDATE Pets SET Weight = :weight, Temperature = :temperature WHERE PetId = :pet_id");
    $stmtPet->execute([':weight' => $weight, ':temperature' => $temperature, ':pet_id' => $petId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Vitals updated successfully.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Database Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>