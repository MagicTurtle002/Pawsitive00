<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* For pets basic info */
/* For pets basic info */
/* For pets basic info */

session_start();

require '../../../config/dbh.inc.php';

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: owner_login.php');
    exit();
}

$owner_id = $_SESSION['OwnerId']; // Get the logged-in owner's ID
$user_name = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Owner';

try {
    // Fetch pets linked to the owner with actual species and breed names
    $query = "SELECT 
                p.PetId, 
                p.Name AS PetName, 
                s.SpeciesName AS PetType, 
                p.Gender, 
                p.CalculatedAge, 
                b.BreedName AS Breed
              FROM pets p
              LEFT JOIN Species s ON p.SpeciesId = s.Id
              LEFT JOIN Breeds b ON p.Breed = b.BreedId
              WHERE p.OwnerId = :OwnerId";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':OwnerId', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch pet types (species) for dropdown
    $speciesStmt = $pdo->query("SELECT Id, SpeciesName FROM Species");
    $petTypes = $speciesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive | Settings</title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="settings.css">

    <script>
        const bookedTimesByDate = <?= json_encode($bookedTimesByDate); ?>;
    </script>
</head>

<body>
    <header>
        <nav>
            <div class="logo">
                <img src="../../../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
            </div>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="../appointment/book_appointment.php">Appointment</a></li>
                <li><a href="pet_add.php">Pets</a></li>
                <li><a href="../record/pet_record.php">Record</a></li>
                <li><a href="../invoice/invoice.php">Invoice</a></li>
            </ul>
            <div class="profile-dropdown">
                <img src="../../../assets/images/Icons/User 1.png" alt="Profile Icon" class="profile-icon">
                <div class="dropdown-content">
                    <a href=""><img src="../../../assets/images/Icons/Settings 2.png" alt="Settings">Settings</a>
                    <a href=""><img src="../../../assets/images/Icons/Sign out.png" alt="Sign Out">Sign Out</a>
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

    <!--    
    <div class="main-content">
        <div class="container">
            <div class="right-section">
            <h2>Your Pets</h2>
            <?php if (!empty($pets)): ?>
            <div class="table-container">
                <table>
                <thead>
                    <tr>
                        <th>Pet ID</th>
                        <th>Pet Name</th>
                        <th>Species</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Breed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pets as $pet): ?>
                    <tr>
                        <td><?= htmlspecialchars($pet['PetId']) ?></td>
                        <td><?= htmlspecialchars($pet['PetName']) ?></td>
                        <td><?= htmlspecialchars($pet['PetType']) ?></td>
                        <td><?= htmlspecialchars($pet['Gender']) ?></td>
                        <td><?= htmlspecialchars($pet['CalculatedAge'] ?? 'No Information Found') ?></td>
                        <td><?= htmlspecialchars($pet['Breed']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
                <?php else: ?>
                    <p>You have no pets registered.</p>
                <?php endif; ?>
          </div>
    </main>
    -->

    <main>
        <div class="main-content">
            <div class="container">
                <!-- Right Section: Add Pet Form -->
                <div class="right-section">

                <h2>My Profile</h2>
         
                <h3 style="margin-bottom: 10px;">Profile Picture</h3>
                <div class="profile-container">
                    <img src="../../../assets/images/Icons/Profile User.png" alt="Profile Picture" class="profile-pic">
                    
                    <div class="profile-actions">
                        <label for="ProfilePicture" class="btn change-btn">Change Picture</label>
                        <input type="file" id="ProfilePicture" name="ProfilePicture" accept="image/*" style="display: none;" onchange="previewProfilePicture(event)">
                        
                        <button class="btn delete-btn" onclick="deleteProfilePicture()">Delete Picture</button>
                    </div>
                </div>
            <div>    
        </div>
    </main>

    <main>
        <div class="main-content">
            <div class="container">
                <!-- Right Section: Add Pet Form -->
                <div class="right-section">

                <h2>Personal Information</h2>
                    <form class="staff-form" action="add_pet.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-row">
                    <div class="input-container">
                        <label for="FirstName">First Name:</label>
                        <input type="text" id="FirstName" name="FirstName"
                            value="<?= htmlspecialchars($form_data['FirstName'] ?? '') ?>"
                            placeholder="" required>
                        <?php if (isset($errors['FirstName'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['FirstName']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="input-container">
                        <label for="LastName">Last Name:</label>
                        <input type="text" id="LastName" name="LastName"
                            value="<?= htmlspecialchars($form_data['LastName'] ?? '') ?>" placeholder=""
                            required>
                        <?php if (isset($errors['LastName'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['LastName']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label for="Email">Email:</label>
                        <input type="email" id="Email" name="Email"
                            value="<?= htmlspecialchars($form_data['Email'] ?? '') ?>" placeholder=""
                            required>
                        <?php if (isset($errors['Email'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="input-container">
                        <label for="Phone">Phone Number:</label>
                        <input type="text" id="Phone" name="Phone"
                            value="<?= isset($form_data['Phone']) ? htmlspecialchars($form_data['Phone']) : '' ?>"
                            placeholder="" required maxlength="13" pattern="^\+63\d{10}$"
                            title="Phone number should start with +63 followed by 10 digits">
                        <?php if (isset($errors['Phone'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Phone']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="window.location.href='../index.html'">Cancel</button>
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
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <!-- Current Password -->
                        <div class="form-row">
                            <div class="input-container">
                                <label for="current_password">Current Password:<span class="required-asterisk">*</span></label>
                                <div class="password-container">
                                    <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                                    <i class="fas fa-eye-slash eye-icon" onclick="togglePassword('current_password', this)"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="input-container">
                                <label for="new_password">New Password:<span class="required-asterisk">*</span></label>
                                <div class="password-container">
                                    <input type="password" id="new_password" name="new_password" placeholder="Enter your new password" required>
                                    <i class="fas fa-eye-slash eye-icon" onclick="togglePassword('new_password', this)"></i>
                                </div>
                                <ul id="password-requirements" class="hidden">
                                    <br>
                                    <li id="req-length"><i class="fas fa-times"></i> At least 8 characters</li>
                                    <li id="req-uppercase"><i class="fas fa-times"></i> At least one uppercase letter</li>
                                    <li id="req-lowercase"><i class="fas fa-times"></i> At least one lowercase letter</li>
                                    <li id="req-digit"><i class="fas fa-times"></i> At least one digit</li>
                                    <li id="req-special"><i class="fas fa-times"></i> At least one special character</li>
                                </ul>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="input-container">
                                <label for="confirm_password">Confirm Password:<span class="required-asterisk">*</span></label>
                                <div class="password-container">
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required onkeyup="validateConfirmPassword()">
                                    <i class="fas fa-eye-slash eye-icon" onclick="togglePassword('confirm_password', this)"></i>
                                </div>
                                <span id="confirm-password-error" style="color: red; display: none;">Passwords do not match.</span>
                            </div>
                        </div>

                        <div class="form-buttons">
                            <button type="button" class="cancel-btn" onclick="window.location.href='../index.html'">Cancel</button>
                            <button type="submit" class="regowner-btn" id="submit-btn">Set New Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
      const profileIcon = document.querySelector('.profile-icon');
      const dropdownContent = document.querySelector('.dropdown-content');

      profileIcon.addEventListener('click', function() {
        dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
      });

      window.addEventListener('click', function(event) {
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


</body>
</html>