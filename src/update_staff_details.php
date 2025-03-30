<?php
require __DIR__ . '/../config/dbh.inc.php';

header("Content-Type: application/json");
$response = ["success" => false, "message" => ""];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Get input values
    $email = $_POST['email'] ?? '';
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $phoneNumber = $_POST['phoneNumber'] ?? '';
    $status = $_POST['status'] ?? '';

    // Debugging log (Check if values are received correctly)
    error_log("Updating Staff: Email: $email, Name: $firstName $lastName, Phone: $phoneNumber, Status: $status");

    if (empty($email) || empty($firstName) || empty($lastName)) {
        throw new Exception("Missing required fields");
    }

    // Update query
    $query = "UPDATE Users SET FirstName = ?, LastName = ?, PhoneNumber = ?, Status = ?, UpdatedAt = NOW() WHERE Email = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$firstName, $lastName, $phoneNumber, $status, $email]);

    if ($stmt->rowCount() > 0) {
        $response["success"] = true;
        $response["message"] = "Staff details updated successfully!";
    } else {
        throw new Exception("No changes made or user not found.");
    }
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
    error_log("Error updating staff: " . $e->getMessage());
}

echo json_encode($response);
?>