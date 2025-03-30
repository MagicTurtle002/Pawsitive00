<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';
require __DIR__ . '/../src/helpers/log_helpers.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$userId = $_SESSION['UserId'];
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $first_name = filter_input(INPUT_POST, "FirstName", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $last_name = filter_input(INPUT_POST, "LastName", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'Email', FILTER_VALIDATE_EMAIL);
    $role_id = filter_input(INPUT_POST, 'RoleId', FILTER_SANITIZE_NUMBER_INT);
    $employment_type = filter_input(INPUT_POST, 'EmploymentType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // **Input Validations**
    $errors = [];

    if (empty($first_name) || strlen($first_name) < 2) {
        $errors['FirstName'] = "First name must be at least 2 characters long.";
    }

    if (empty($last_name) || strlen($last_name) < 2) {
        $errors['LastName'] = "Last name must be at least 2 characters long.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['Email'] = "Valid email address is required.";
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header('Location: ../public/add_staff.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // **Check if email already exists**
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE Email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['errors'] = ['Email' => "Email already exists."];
            header("Location: ../public/add_staff.php");
            exit;
        }

        // **Generate and Hash Temporary Password**
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        $verification_code = bin2hex(random_bytes(16));

        // **Insert User into `Users` table**
        $stmt = $pdo->prepare("
            INSERT INTO Users (FirstName, LastName, Email, Password, RoleId, EmploymentType, Status, CreatedAt, VerificationCode)
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), ?)
        ");
        $stmt->execute([$first_name, $last_name, $email, $hashed_password, $role_id, $employment_type, $verification_code]);

        // **Get Newly Created User ID**
        $user_id = $pdo->lastInsertId();

        // **Assign Role to User in `UserRoles`**
        $stmt = $pdo->prepare("INSERT INTO UserRoles (UserId, RoleId) VALUES (?, ?)");
        $stmt->execute([$user_id, $role_id]);

        $stmt = $pdo->prepare("
            INSERT INTO RolePermissions (RoleId, PermissionId) 
            SELECT :roleId, 24 FROM DUAL 
            WHERE NOT EXISTS (
                SELECT 1 FROM RolePermissions 
                WHERE RoleId = :roleIdCheck AND PermissionId = 24
            )
        ");
        $stmt->execute([
            ':roleId' => $role_id,
            ':roleIdCheck' => $role_id
        ]);

        $pdo->commit();

        // **Log Activity**
        logActivity(
            $pdo,
            $userId,
            $userName,
            $role,
            'Staff Management',
            "Added new staff: $first_name $last_name ($email)"
        );

        $verification_link = "https://www.vetpawsitive.com/src/verify_account.php?email=" . urlencode($email) . "&code=$verification_code";

        // **Send Welcome Email with Temporary Password**
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'danvincentteodoro11@gmail.com';
        $mail->Password = 'fhvt onlo hdwm wjlx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 0;

        $mail->setFrom('danvincentteodoro11@gmail.com', 'Pawsitive HR');
        $mail->addReplyTo('danvincentteodoro11@gmail.com', 'Pawsitive Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Welcome to Pawsitive - Your Account Details";

        $login_link = "https://www.vetpawsitive.com/public/staff_login.php";
        $contact_email = "danvincentteodoro11@gmail.com";

        $mail->Body = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .header { text-align: center; padding: 10px 0; }
        .header img { width: 150px; }
        .content { padding: 20px; font-size: 16px; color: #333333; line-height: 1.6; }
        .content h2 { color: #198754; }
        .button { display: block; width: 200px; text-align: center; margin: 20px auto; padding: 10px; background-color: #198754; color: #ffffff; text-decoration: none; font-size: 18px; border-radius: 5px; }
        .footer { text-align: center; font-size: 14px; color: #777777; padding: 10px 0; border-top: 1px solid #eeeeee; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <img src='https://www.vetpawsitive.com/assets/images/logo/LOGO.png' alt='Pawsitive Logo'>
        </div>
        <div class='content'>
            <h2>Welcome to Pawsitive, $first_name!</h2>
            <p>Your account has been successfully created. To complete the setup, please verify your account.</p>
            
            <p><strong>Your Account Details:</strong></p>
            <ul>
                <li><strong>Email:</strong> $email</li>
                <li><strong>Temporary Password:</strong> <span style='background:#f8f9fa;padding:5px;border-radius:4px;'>$temp_password</span></li>
            </ul>
            
            <p><strong>Click the button below to verify your account:</strong></p>
            <a class='button' href='$verification_link'>Verify Account</a>
            
            <p>If you have any questions, feel free to contact our support team at <a href='mailto:support@pawsitive.com'>support@pawsitive.com</a>.</p>

            <p>Best regards,</p>
            <p><strong>The Pawsitive Team</strong></p>
        </div>
        <div class='footer'>
            &copy; " . date('Y') . " Pawsitive. All rights reserved.
        </div>
    </div>
</body>
</html>
";

        $mail->send();

        $_SESSION['success'] = "Added new staff: $first_name $last_name with email $email";
        header("Location: ../public/staff.php?success=1");
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['errors'] = ["Database" => "An error occurred. Please try again."];
        header("Location: ../public/add_staff.php");
        exit;
    }
}
?>