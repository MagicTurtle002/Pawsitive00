<?php
require_once __DIR__ . '/../config/dbh.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['petId'])) {
    $petId = (int)$_POST['petId'];

    try {
        $query = "UPDATE Pets SET IsArchived = 0 WHERE PetId = ?";
        $stmt = $pdo->prepare($query);

        if ($stmt->execute([$petId])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to restore pet.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
}
?>