<?php
require '../config/dbh.inc.php';

$pet_id = $_GET['pet_id'] ?? null;
if (!$pet_id) {
    echo json_encode([]);
    exit;
}

try {
    $query = "SELECT * FROM PatientRecords WHERE PetId = ? ORDER BY CreatedAt DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$pet_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($records);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([]);
}
?>