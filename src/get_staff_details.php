<?php
require __DIR__ . '/../config/dbh.inc.php'; // Ensure DB connection

header('Content-Type: application/json');

// Check if email parameter is set
if (!isset($_GET['email']) || empty($_GET['email'])) {
    echo json_encode(["error" => "No email provided"]);
    exit;
}

$email = $_GET['email'];

try {
    // Prepare SQL to fetch staff details
    $stmt = $pdo->prepare("SELECT Email, FirstName, LastName, PhoneNumber FROM Users WHERE Email = ?");
    $stmt->execute([$email]);
    
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if staff exists
    if (!$staff) {
        echo json_encode(["error" => "Staff not found"]);
        exit;
    }

    echo json_encode($staff);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(["error" => "Database error"]);
}
?>