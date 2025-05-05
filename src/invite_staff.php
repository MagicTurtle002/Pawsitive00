<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
require '../vendor/autoload.php';
require 'helpers/log.inc.php';
require 'helpers/invitation_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if ($csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['errors'] = ["Invalid request. Please refresh the page."];
        header("Location: ../public/invite_staff.php");
        exit();
    }

    $emails = $_POST['email'];
    $role_ids = $_POST['role_id'];
    $employment_types = $_POST['employment_type'];

    $errors = [];
    $success_count = 0;

    try {
        $pdo->beginTransaction();

        foreach ($emails as $index => $email) {
            $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            $role_id = intval($role_ids[$index]);
            $employment_type = trim($employment_types[$index]);

            if (!$email) {
                $errors[] = "Invalid email format at entry #".($index + 1).". Please provide a valid email address.";
                continue;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "The email {$email} is already registered.";
                continue;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM StaffInvitations WHERE Email = ? AND Status = 'Pending'");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "An invitation has already been sent to {$email}.";
                continue;
            }

            $stmt = $pdo->prepare("SELECT RoleName FROM Roles WHERE RoleId = ?");
            $stmt->execute([$role_id]);
            $role_name = $stmt->fetchColumn();
            if (!$role_name) {
                $errors[] = "Role not found: Invalid Role ID {$role_id}.";
                continue;
            }

            $allowedEmploymentTypes = ['Full-Time', 'Part-Time', 'Contractor'];
            if (!in_array($employment_type, $allowedEmploymentTypes)) {
                $errors[] = "Invalid employment type: {$employment_type}.";
                continue;
            }

            [$invErrors, $roleId] = validateStaffInvitation($pdo, $email, $role_name, $employment_type);
            if (!empty($invErrors)) {
                $errors = array_merge($errors, $invErrors);
                continue;
            }
            $mail = new PHPMailer(true);
            try {
                $token = bin2hex(random_bytes(32));

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'danvincentteodoro11@gmail.com';
                $mail->Password = 'fhvt onlo hdwm wjlx';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('danvincentteodoro11@gmail.com', 'Clinic Admin');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = "You're Invited to Join Pet Adventure Team!";

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                        <h2 style='color: #198754;'>Welcome to VetPawsitive!</h2>
                        <p>Dear Candidate,</p>

                        <p>We are excited to invite you to join our veterinary clinic as a <strong>{$role_name}</strong> with the employment type of <strong>{$employment_type}</strong>.</p>

                        <p>To complete your registration and get started with us, please click the button below:</p>

                        <p style='text-align: center;'>
                            <a href='https://vetpawsitive.com/public/onboarding_new_password.php?token={$token}' 
                            style='background-color: #198754; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                                Complete Your Registration
                            </a>
                        </p>

                        <p><strong>Note:</strong> This invitation will expire in <strong>7 days</strong>, so please register as soon as possible.</p>

                        <p>If you have any questions, feel free to reply to this email or contact our support team.</p>

                        <p>Best regards, <br>
                        <strong>Pet Adventure Admin</strong></p>
                    </div>
                ";

                $mail->send();
                $stmt = $pdo->prepare("INSERT INTO StaffInvitations (Email, RoleId, EmploymentType, Token, Status, ExpiresAt) 
                                VALUES (?, ?, ?, ?, 'Pending', DATE_ADD(NOW(), INTERVAL 7 DAY))");
                $stmt->execute([$email, $role_id, $employment_type, $token]);
                $success_count++;

            } catch (Exception $e) {
                error_log("Mailer Error for {$email}: " . $mail->ErrorInfo);
                $errors[] = "Failed to send invitation to {$email}.";
            }
        }

        if ($success_count > 0) {
            $message = $success_count === 1
                ? "1 invitation sent successfully."
                : "{$success_count} invitations sent successfully.";
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['success'] = ""; // No success if no email was sent
        }

        $userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
        logActivity($pdo, $_SESSION['UserId'], $userName, $_SESSION['Role'], 'invite_staff.php', 'Completed onboarding and invited staff.');
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Transaction Error: " . $e->getMessage());
        $errors[] = "An error occurred. Please try again.";
    }
    $_SESSION['errors'] = $errors;
    header("Location: ../public/staff_invitation_form.php");
    exit();
}
?>