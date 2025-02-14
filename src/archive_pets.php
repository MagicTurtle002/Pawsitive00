<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/dbh.inc.php';
require_once 'helpers/log_helpers.php'; // Include logging function

session_start();

$response = ['success' => false];

if (!isset($_SESSION['LoggedIn']) || !isset($_SESSION['UserId'])) {
    die(json_encode(["success" => false, "error" => "Unauthorized access."]));
}

if (isset($_GET['pet_id']) && is_numeric($_GET['pet_id'])) {
    $petId = (int) $_GET['pet_id'];
    $userId = $_SESSION['UserId'];
    $userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
    $role = $_SESSION['Role'] ?? 'Role';

    try {
        $stmt = $pdo->prepare("UPDATE Pets SET IsArchived = 1 WHERE PetId = ?");
        if ($stmt->execute([$petId])) {
            $response['success'] = true;

            // Log the archiving action
            //logActivity($pdo, $userId, $userName, $role, 'archive_pet.php', "Archived pet with ID: $petId");
        }
    } catch (PDOException $e) {
        error_log("Error archiving pet: " . $e->getMessage());
        $response['error'] = "Database error occurred.";
    }
}

header("Location: ../public/record.php?archived=success");
exit;
?>