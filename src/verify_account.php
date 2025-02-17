<?php
require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/log_helpers.php';

if (isset($_GET['email']) && isset($_GET['code'])) {
    // Sanitize input
    $email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);
    $code = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_SPECIAL_CHARS);

    if (!$email || !$code) {
        header("Location: ../public/staff_login.php?error=invalid_request");
        exit;
    }

    try {
        // Check if the email and verification code match
        $stmt = $pdo->prepare("SELECT UserId FROM Users WHERE Email = ? AND VerificationCode = ? AND IsVerified = 0");
        $stmt->execute([$email, $code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update user to verified
            $stmt = $pdo->prepare("UPDATE Users SET IsVerified = 1, VerificationCode = NULL WHERE Email = ?");
            $stmt->execute([$email]);

            // Log verification event
            //logActivity($pdo, $user['UserId'], 'System', 'Verification', 'User verified their account');

            // ✅ SUCCESS: Redirect to login page with auto-reload
            echo "<script>
                alert('Account verified successfully! Redirecting to login page...');
                window.location.href = '../public/staff_login.php?verified=1';
            </script>";
            exit;
        } else {
            header("Location: ../public/staff_login.php?error=invalid_verification");
            exit;
        }
    } catch (Exception $e) {
        error_log("Verification Error: " . $e->getMessage());
        header("Location: ../public/staff_login.php?error=server_error");
        exit;
    }
} else {
    header("Location: ../public/staff_login.php?error=missing_parameters");
    exit;
}
?>