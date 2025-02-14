<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/dbh.inc.php';

// Debugging
error_log("Delete Pet Script Called");

// Ensure the user is logged in
if (!isset($_SESSION['LoggedIn'])) {
    error_log("User not logged in, redirecting...");
    header('Location: staff_login.php');
    exit;
}

// Validate `PetId`
if (!isset($_GET['PetId']) || !is_numeric($_GET['PetId'])) {
    error_log("Invalid Pet ID: " . ($_GET['PetId'] ?? 'NULL'));
    $_SESSION['error'] = 'Invalid Pet ID.';
    header('Location: ../public/record.php');
    exit;
}

$petId = (int)$_GET['PetId'];

try {
    error_log("Attempting to delete Pet ID: " . $petId);

    // Check if the pet exists and is not archived already
    $query = "SELECT PetId FROM Pets WHERE PetId = ? AND IsArchived = 0";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$petId]);

    if ($stmt->rowCount() === 0) {
        error_log("Pet not found or already archived: " . $petId);
        $_SESSION['error'] = 'Pet not found or already deleted.';
        header('Location: ../public/record.php');
        exit;
    }

    // Perform the soft delete (archive)
    $deleteQuery = "UPDATE Pets SET IsArchived = 1 WHERE PetId = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$petId]);

    if ($deleteStmt->rowCount() > 0) {
        error_log("Pet deleted successfully: " . $petId);
        $_SESSION['success'] = 'Pet record deleted successfully.';
    } else {
        error_log("Delete failed: " . $petId);
        $_SESSION['error'] = 'Failed to delete the pet record.';
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
}

// Redirect
header('Location: ../public/record.php');
exit;