<?php
session_start();
require '../config/dbh.inc.php';

if (!isset($_GET['token'])) {
    die("Invalid request.");
}

$token = $_GET['token'];

try {
    $stmt = $pdo->prepare("SELECT OwnerId FROM Owners WHERE VerificationToken = :Token");
    $stmt->execute([':Token' => $token]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$owner) {
        die("Invalid verification link.");
    }

    // Update email verification status
    $update_stmt = $pdo->prepare("UPDATE Owners SET EmailVerified = 1, VerificationToken = NULL WHERE OwnerId = :OwnerId");
    $update_stmt->execute([':OwnerId' => $owner['OwnerId']]);

    $_SESSION['success'] = "✅ Your email has been verified!";
    header("Location: ../public/owner_login.php");
    exit();
} catch (PDOException $e) {
    error_log("Error verifying email: " . $e->getMessage());
    die("An error occurred. Please try again.");
}
?>