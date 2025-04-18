<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); // Ensure session is started

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

$errors = [];
$successMessage = "";

// Ensure the user is logged in
if (!isset($_SESSION['Email'])) {
    header("Location: staff_login.php");
    exit();
}

$email = $_SESSION['Email']; // Fetch logged-in user's email

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // **Validate Input**
    if (empty($new_password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

            // **Update User Password & Onboarding Status**
            $stmt = $pdo->prepare("
                UPDATE Users 
                SET Password = ?, FirstLogin = 0, OnboardingComplete = 1 
                WHERE Email = ?
            ");
            $stmt->execute([$hashedPassword, $email]);

            $_SESSION['success'] = "Password updated successfully! You can now log in.";
            header("Location: staff_login.php");
            exit();
        } catch (Exception $e) {
            error_log("Error updating password: " . $e->getMessage());
            $errors[] = "An error occurred while updating the password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Pawsitive, the pet management system.">
    <meta name="keywords" content="Pawsitive, Pet Management, Login">
    <meta name="author" content="Pawsitive">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/staff_login.css">
    <script src="../assets/js/index.js" defer></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            width: 50%;
            border-radius: 8px;
            text-align: center;
        }

        .agree-btn, .cancel-btn {
            padding: 10px 20px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .agree-btn {
            background-color: #28a745;
            color: white;
        }

        .cancel-btn {
            background-color: #dc3545;
            color: white;
        }

        .agree-btn:hover {
            background-color: #218838;
        }

        .cancel-btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="left-panel">
            <h2 class="welcome-message">Hello! Welcome to</h2>
            <div class="branding">
                <img src="../assets/images/logo/LOGO 1 WHITE.png" alt="Pawsitive Logo" class="logo">
                <p>Your all-in-one system management<br>pet records, appointments, and more!</p>
            </div>
        </div>
        <div class="right-panel">
            <h3 class="form-title">Set Your New Password</h3>
            <form action="onboarding_new_password.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <?php if (!empty($successMessage)): ?>
                    <div class="success-notification">
                        <ul>
                            <li><i class="fas fa-check-circle"></i> 
                                <?= htmlspecialchars($successMessage) ?>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="error-notification">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="new_password">New Password:<span class="required-asterisk">*</span></label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye eye-icon" onclick="togglePassword('password', this)"></i>
                    </div>
                </div>
                <ul id="password-requirements">
                        <li id="req-length" class="requirement">
                            <i class="fas fa-times"></i> At least 8 characters
                        </li>
                        <li id="req-uppercase" class="requirement">
                            <i class="fas fa-times"></i> At least one uppercase letter
                        </li>
                        <li id="req-lowercase" class="requirement">
                            <i class="fas fa-times"></i> At least one lowercase letter
                        </li>
                        <li id="req-digit" class="requirement">
                            <i class="fas fa-times"></i> At least one digit
                        </li>
                        <li id="req-special" class="requirement">
                            <i class="fas fa-times"></i> At least one special character
                        </li>
                    </ul>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:<span class="required-asterisk">*</span></label>
                        <div class="password-container">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                            <i class="fas fa-eye eye-icon" onclick="togglePassword('confirm_password', this)"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="login-btn">Set Password</button>
                </div>
            </form>
        </div>
    </div>
    <script src="../assets/js/password.js"></script>
    <script src="../assets/js/index.js"></script>
</body>
</html>