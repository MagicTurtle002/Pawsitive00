<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
session_start();

$response = ['success' => false];

if (!isset($_SESSION['LoggedIn'])) {
    $response['error'] = 'User not logged in.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilePicture'], $_POST['petId'])) {
    $petId = (int) $_POST['petId'];
    $ownerId = $_SESSION['OwnerId'] ?? null;

    if (!$ownerId) {
        $response['error'] = 'Owner ID not found.';
        echo json_encode($response);
        exit;
    }

    // Ensure the upload directory exists
    $uploadDir = '../uploads/pet_avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        $response['error'] = 'Upload directory is not writable. Check folder permissions.';
        echo json_encode($response);
        exit;
    }

    $file = $_FILES['profilePicture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB max size

    // Handle upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The file is too large (server limit).',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the form size limit.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by an extension.'
        ];
        $response['error'] = $uploadErrors[$file['error']] ?? 'Unknown file upload error.';
        echo json_encode($response);
        exit;
    }

    if (!in_array($file['type'], $allowedTypes)) {
        $response['error'] = 'Invalid file type. Only JPG, PNG, and GIF allowed.';
    } elseif ($file['size'] > $maxSize) {
        $response['error'] = 'File size exceeds the 5MB limit.';
    } else {
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = "pet_{$petId}_" . time() . "." . $fileExt;
        $filePath = $uploadDir . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Update database with new image name
            $query = "UPDATE Pets SET ProfilePicture = :profileImage WHERE PetId = :petId AND OwnerId = :ownerId";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':profileImage', $newFileName, PDO::PARAM_STR);
            $stmt->bindParam(':petId', $petId, PDO::PARAM_INT);
            $stmt->bindParam(':ownerId', $ownerId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['filePath'] = $filePath;
            } else {
                $response['error'] = 'Database update failed.';
            }
        } else {
            $response['error'] = 'Failed to move uploaded file.';
        }
    }
} else {
    $response['error'] = 'Invalid request.';
}

echo json_encode($response);