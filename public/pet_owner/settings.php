<?php
session_start();
require '../../config/dbh.inc.php'; // Database connection

// Redirect to login if user is not logged in
if (!isset($_SESSION['LoggedIn']) || !isset($_SESSION['OwnerId'])) {
    header('Location: owner_login.php');
    exit();
}

$owner_id = $_SESSION['OwnerId']; // Get logged-in owner's ID
$user_name = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];

try {
    // Fetch pet owner details
    $query = "SELECT FirstName, LastName, Email, Phone FROM Owners WHERE OwnerId = :OwnerId";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':OwnerId', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$owner) {
        die("Owner information not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Profile picture logic
$profile_picture = (!empty($owner['ProfilePicture']) && file_exists("../../uploads/owner_avatars/" . $owner['ProfilePicture']))
    ? "../../uploads/owner_avatars/" . htmlspecialchars($owner['ProfilePicture'])
    : "../../assets/images/Icons/Profile User.png";
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive | Settings</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/settings.css">

    <script>
        const bookedTimesByDate = <?= json_encode($bookedTimesByDate); ?>;
    </script>
</head>

<body>
    <header>
        <nav>
            <div class="logo">
                <img src="../../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="book_appointment.php">Appointment</a></li>
                <li><a href="pet_add.php">Pets</a></li>
                <li><a href="pet_record.php">Record</a></li>
                <li><a href="invoice.php">Invoice</a></li>
            </ul>
            <div class="profile-dropdown">
                <img src="../../assets/images/Icons/User 1.png" alt="Profile Icon" class="profile-icon">
                <div class="dropdown-content">
                    <a href="settings.php"><img src="../../assets/images/Icons/Settings 2.png"
                            alt="Settings">Settings</a>
                    <a href=""><img src="../../assets/images/Icons/Sign out.png" alt="Sign Out">Sign Out</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-text">
                <h1>Settings</h1>
            </div>
        </section>

        <main>
            <div class="main-content">
                <div class="container">
                    <!-- Right Section: Add Pet Form -->
                    <div class="right-section">

                        <h2>Personal Information</h2>
                        <form class="staff-form" action="../../src/update_owner.php" method="POST">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="form-row">
                                <div class="input-container">
                                    <label for="FirstName">First Name:<span
                                    class="required-asterisk">*</span></label>
                                    <input type="text" id="FirstName" name="FirstName"
                                        value="<?= htmlspecialchars($owner['FirstName']) ?>" readonly>
                                </div>

                                <div class="input-container">
                                    <label for="LastName">Last Name:<span
                                    class="required-asterisk">*</span></label>
                                    <input type="text" id="LastName" name="LastName"
                                        value="<?= htmlspecialchars($owner['LastName']) ?>" readonly>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label for="Email">Email:<span
                                    class="required-asterisk">*</span></label>
                                    <input type="email" id="Email" name="Email"
                                        value="<?= htmlspecialchars($owner['Email']) ?>" required
                                        onchange="confirmEmailChange()">
                                </div>

                                <div class="input-container">
                                    <label for="Phone">Phone Number:<span
                                    class="required-asterisk">*</span></label>
                                    <input type="text" id="Phone" name="Phone"
                                        value="<?= htmlspecialchars($owner['Phone']) ?>" required maxlength="13"
                                        pattern="^\+63\d{10}$">
                                </div>
                            </div>

                            <div class="form-buttons">
                                <button type="button" class="cancel-btn"
                                    onclick="window.location.href='../index.html'">Cancel</button>
                                <button type="submit" class="regowner-btn">Edit Profile</button>
                            </div>
                        </form>
                    </div>
        </main>

        <main>
            <div class="main-content">
                <div class="container">
                    <div class="right-section">
                        <h2>Change Password</h2>
                        <form class="staff-form" action="change_password.php" method="POST">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <!-- Current Password -->
                            <div class="form-row">
                                <div class="input-container">
                                    <label for="current_password">Current Password:<span
                                            class="required-asterisk">*</span></label>
                                    <div class="password-container">
                                        <input type="password" id="current_password" name="current_password"
                                            placeholder="Enter your current password" required>
                                        <i class="fas fa-eye-slash eye-icon"
                                            onclick="togglePassword('current_password', this)"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label for="new_password">New Password:<span
                                            class="required-asterisk">*</span></label>
                                    <div class="password-container">
                                        <input type="password" id="new_password" name="new_password"
                                            placeholder="Enter your new password" required>
                                        <i class="fas fa-eye-slash eye-icon"
                                            onclick="togglePassword('new_password', this)"></i>
                                    </div>
                                    <ul id="password-requirements" class="hidden">
                                        <br>
                                        <li id="req-length"><i class="fas fa-times"></i> At least 8 characters</li>
                                        <li id="req-uppercase"><i class="fas fa-times"></i> At least one uppercase
                                            letter</li>
                                        <li id="req-lowercase"><i class="fas fa-times"></i> At least one lowercase
                                            letter</li>
                                        <li id="req-digit"><i class="fas fa-times"></i> At least one digit</li>
                                        <li id="req-special"><i class="fas fa-times"></i> At least one special character
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label for="confirm_password">Confirm Password:<span
                                            class="required-asterisk">*</span></label>
                                    <div class="password-container">
                                        <input type="password" id="confirm_password" name="confirm_password"
                                            placeholder="Confirm your password" required
                                            onkeyup="validateConfirmPassword()">
                                        <i class="fas fa-eye-slash eye-icon"
                                            onclick="togglePassword('confirm_password', this)"></i>
                                    </div>
                                    <span id="confirm-password-error" style="color: red; display: none;">Passwords do
                                        not match.</span>
                                </div>
                            </div>

                            <div class="form-buttons">
                                <button type="button" class="cancel-btn"
                                    onclick="window.location.href='../index.html'">Cancel</button>
                                <button type="submit" class="regowner-btn" id="submit-btn">Set New Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <script>
            function confirmEmailChange() {
                let emailInput = document.getElementById('Email');
                let originalEmail = "<?= htmlspecialchars($owner['Email']) ?>";

                if (emailInput.value !== originalEmail) {
                    Swal.fire({
                        title: "Are you sure?",
                        text: "Changing your email will update your login credentials. Make sure your new email is active.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, I understand",
                        cancelButtonText: "Cancel",
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            emailInput.value = originalEmail; // Reset to previous email if user cancels
                        }
                    });
                }
            }
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const profileIcon = document.querySelector('.profile-icon');
                const dropdownContent = document.querySelector('.dropdown-content');

                profileIcon.addEventListener('click', function () {
                    dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
                });

                window.addEventListener('click', function (event) {
                    if (!event.target.matches('.profile-icon')) {
                        if (dropdownContent.style.display === 'block') {
                            dropdownContent.style.display = 'none';
                        }
                    }
                });
            });
        </script>

        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const passwordInput = document.getElementById("new_password");
                const confirmPasswordInput = document.getElementById("confirm_password");
                const confirmPasswordGroup = confirmPasswordInput.closest(".form-group");
                const requirementsList = document.getElementById("password-requirements");

                const reqLength = document.getElementById("req-length");
                const reqUppercase = document.getElementById("req-uppercase");
                const reqLowercase = document.getElementById("req-lowercase");
                const reqDigit = document.getElementById("req-digit");
                const reqSpecial = document.getElementById("req-special");

                const lengthPattern = /.{8,}/;
                const uppercasePattern = /[A-Z]/;
                const lowercasePattern = /[a-z]/;
                const digitPattern = /\d/;
                const specialPattern = /[\W_]/;

                // Function to update the requirement status
                function updateRequirement(element, isValid) {
                    const icon = element.querySelector("i");
                    if (isValid) {
                        icon.classList.remove("fa-times");
                        icon.classList.add("fa-check");
                        element.classList.add("valid");
                        element.classList.remove("invalid");
                    } else {
                        icon.classList.remove("fa-check");
                        icon.classList.add("fa-times");
                        element.classList.add("invalid");
                        element.classList.remove("valid");
                    }
                }

                // Show password validation list when focusing on password input
                passwordInput.addEventListener("focus", () => {
                    requirementsList.classList.add("visible");
                    confirmPasswordGroup.style.marginTop = "20px"; // Push down Confirm Password field
                });

                // Hide password validation list if the input is empty on blur
                passwordInput.addEventListener("blur", () => {
                    if (!passwordInput.value) {
                        requirementsList.classList.remove("visible");
                        confirmPasswordGroup.style.marginTop = "0"; // Reset Confirm Password field position
                    }
                });

                // Update validation requirements dynamically as the user types
                passwordInput.addEventListener("input", () => {
                    const password = passwordInput.value;

                    updateRequirement(reqLength, lengthPattern.test(password));
                    updateRequirement(reqUppercase, uppercasePattern.test(password));
                    updateRequirement(reqLowercase, lowercasePattern.test(password));
                    updateRequirement(reqDigit, digitPattern.test(password));
                    updateRequirement(reqSpecial, specialPattern.test(password));
                });
            });

            // Toggle password visibility
            function togglePassword(fieldId, eyeIcon) {
                const passwordField = document.getElementById(fieldId);
                const isPasswordVisible = passwordField.type === "text";

                // Toggle input field type
                passwordField.type = isPasswordVisible ? "password" : "text";

                // Toggle eye icon class
                eyeIcon.classList.toggle("fa-eye-slash", isPasswordVisible);
                eyeIcon.classList.toggle("fa-eye", !isPasswordVisible);
            }

            // Ensure Confirm Password matches New Password
            function validateConfirmPassword() {
                let password = document.getElementById("new_password").value;
                let confirmPassword = document.getElementById("confirm_password").value;
                let confirmError = document.getElementById("confirm-password-error");

                if (confirmPassword !== password) {
                    confirmError.style.display = "block";
                } else {
                    confirmError.style.display = "none";
                }
            }
        </script>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                <?php if (isset($_SESSION['warning'])): ?>
                    Swal.fire({
                        title: "Verify Your Email",
                        text: "Please check your email to verify your account.",
                        icon: "warning",
                        confirmButtonText: "OK"
                    });
                    <?php unset($_SESSION['warning']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    Swal.fire({
                        title: "Success!",
                        text: "<?= $_SESSION['success']; ?>",
                        icon: "success",
                        confirmButtonText: "OK"
                    });
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
            });
        </script>

</body>

</html>