<?php
session_start();
require '../config/dbh.inc.php'; // Database connection
require '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['LoggedIn']) || !isset($_SESSION['OwnerId'])) {
    header('Location: ../public/owner_login.php');
    exit();
}

$owner_id = $_SESSION['OwnerId'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

    $new_email = trim($_POST['Email']);
    $new_phone = trim($_POST['Phone']);

    try {
        $stmt = $pdo->prepare("SELECT Email FROM Owners WHERE OwnerId = :OwnerId");
        $stmt->execute([':OwnerId' => $owner_id]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);

        // If email is changed, send verification email
        if ($owner['Email'] !== $new_email) {
            $token = bin2hex(random_bytes(32));

            // Store token and mark email as unverified
            $update_stmt = $pdo->prepare("
                UPDATE Owners 
                SET Email = :Email, Phone = :Phone, VerificationToken = :Token, EmailVerified = 0 
                WHERE OwnerId = :OwnerId
            ");
            $update_stmt->execute([
                ':Email' => $new_email,
                ':Phone' => $new_phone,
                ':Token' => $token,
                ':OwnerId' => $owner_id
            ]);

            // Send verification email via PHPMailer
            $verification_link = "https://yourwebsite.com/verify_email.php?token=$token";

            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.your-email-provider.com'; // Change to your SMTP server
                $mail->SMTPAuth = true;
                $mail->Username = 'your-email@example.com'; // Your SMTP username
                $mail->Password = 'your-email-password'; // Your SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Encryption
                $mail->Port = 587; // SMTP Port

                // Recipients
                $mail->setFrom('no-reply@yourwebsite.com', 'Pawsitive');
                $mail->addAddress($new_email);

                // Email content
                $mail->isHTML(true);
                $mail->Subject = "Verify Your Email Address";
                $mail->Body = "
                    <h3>Email Verification</h3>
                    <p>Please verify your email by clicking the button below:</p>
                    <a href='$verification_link' 
                       style='display: inline-block; padding: 10px 20px; background: #28a745; color: #fff; text-decoration: none; border-radius: 5px;'>Verify Email</a>
                    <p>If you did not request this change, please ignore this email.</p>
                ";

                $mail->send();
                $_SESSION['warning'] = "⚠ Please check your email to verify your account.";
            } catch (Exception $e) {
                error_log("Email sending failed: " . $mail->ErrorInfo);
                $_SESSION['errors']['email'] = "Failed to send verification email. Please try again.";
            }
        } else {
            // Update phone only if email remains unchanged
            $update_stmt = $pdo->prepare("UPDATE Owners SET Phone = :Phone WHERE OwnerId = :OwnerId");
            $update_stmt->execute([
                ':Phone' => $new_phone,
                ':OwnerId' => $owner_id
            ]);
        }

        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: ../settings.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error updating owner info: " . $e->getMessage());
        $_SESSION['errors']['database'] = "Something went wrong. Please try again.";
        header("Location: ../settings.php");
        exit();
    }
}
?>