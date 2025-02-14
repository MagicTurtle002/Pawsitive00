<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/dbh.inc.php';

// Ensure the user is logged in
if (!isset($_SESSION['LoggedIn'])) {
    header('Location: staff_login.php');
    exit;
}

// Validate the `PetId` parameter
if (!isset($_GET['PetId']) || !is_numeric($_GET['PetId'])) {
    $_SESSION['error'] = 'Invalid Pet ID.';
    header('Location: ../public/record.php');
    exit;
}

$petId = (int)$_GET['PetId'];

try {
    // Check if the pet exists and is not already archived
    $query = "SELECT PetId FROM Pets WHERE PetId = ? AND IsArchived = 0";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$petId]);

    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = 'Pet not found or already deleted.';
        header('Location: ../public/record.php');
        exit;
    }

    // Perform the deletion (or archive instead of physical deletion)
    $deleteQuery = "UPDATE Pets SET IsArchived = 1 WHERE PetId = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$petId]);

    if ($deleteStmt->rowCount() > 0) {
        $_SESSION['success'] = 'Pet record deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete the pet record.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    error_log('Delete Record Error: ' . $e->getMessage());
}

// Redirect back to the record page
header('Location: ../public/record.php');
exit;