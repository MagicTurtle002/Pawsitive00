<?php
require __DIR__ . '/../config/dbh.inc.php';

header("Content-Type: application/json");
$response = ["success" => false, "message" => ""];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Invalid request method.");
    }

    if (!isset($_GET['email']) || empty($_GET['email'])) {
        throw new Exception("Missing email parameter.");
    }

    $email = $_GET['email'];

    // Debugging log to check if the email is being received correctly
    error_log("Deleting staff with email: $email");

    // Delete staff query
    $query = "DELETE FROM Users WHERE Email = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $response["success"] = true;
        $response["message"] = "Staff member deleted successfully!";
    } else {
        throw new Exception("Staff member not found or already deleted.");
    }
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
    error_log("Error deleting staff: " . $e->getMessage());
}

echo json_encode($response);
?>