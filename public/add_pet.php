<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';
require_once __DIR__ . '/../src/helpers/log_helpers.php';  // Update path if necessary

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = $_SESSION['errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['errors'], $_SESSION['form_data']);

if (isset($_GET['owner_id']) && is_numeric($_GET['owner_id'])) {
    $_SESSION['owner_id'] = (int) $_GET['owner_id'];
} elseif (isset($_POST['OwnerId']) && is_numeric($_POST['OwnerId'])) {
    $_SESSION['owner_id'] = (int) $_POST['OwnerId'];
}

$owner_id = $_SESSION['owner_id'] ?? null;

if ($owner_id === null || $owner_id <= 0) {
    $_SESSION['errors']['owner_id'] = 'Owner ID is required.';
}

try {
    $stmt = $pdo->prepare("SELECT CONCAT(FirstName, ' ', LastName) AS OwnerName FROM Owners WHERE OwnerId = :OwnerId");
    $stmt->execute([':OwnerId' => $owner_id]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $owner_name = $owner['OwnerName'] ?? 'Unknown Owner';
} catch (PDOException $e) {
    error_log("Error fetching owner: " . $e->getMessage());
    $owner_name = 'Unknown Owner';
}

$petTypes = [];
try {
    $stmt = $pdo->query("SELECT Id, SpeciesName FROM Species");
    $petTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pet types: " . $e->getMessage());
}

$species_id = isset($_POST['SpeciesId']) ? filter_var($_POST['SpeciesId'], FILTER_VALIDATE_INT) : 0;

// Fetch the species name using the species_id
$species_name = 'Unknown';
if ($species_id > 0) {
    $stmt = $pdo->prepare("SELECT SpeciesName FROM Species WHERE Id = :species_id");
    $stmt->execute([':species_id' => $species_id]);
    $species = $stmt->fetch(PDO::FETCH_ASSOC);
    $species_name = $species['SpeciesName'] ?? 'Unknown';
}

// Ensure the breed ID is extracted and validated
$breed_id = isset($_POST['Breed']) ? filter_var($_POST['Breed'], FILTER_VALIDATE_INT) : 0;

// Fetch the breed name using the breed ID
$breed_name = 'Unknown';
if ($breed_id > 0) {
    $stmt = $pdo->prepare("SELECT BreedName FROM Breeds WHERE BreedId = :breed_id");
    $stmt->execute([':breed_id' => $breed_id]);
    $breedData = $stmt->fetch(PDO::FETCH_ASSOC);
    $breed_name = $breedData['BreedName'] ?? 'Unknown';
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token.');
    }

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errors']['csrf'] = 'Invalid CSRF token. Please try again.';
        header("Location: add_pet.php?owner_id=$owner_id");
        exit();
    }

    $errors = []; // Initialize error array

    // Ensure form data is stored so it persists
    $_SESSION['form_data'] = $_POST;

    $pet_name = sanitizeInput($_POST['Name'] ?? '');
    $species_id = filter_var($_POST['SpeciesId'] ?? 0, FILTER_VALIDATE_INT);
    $breed = sanitizeInput($_POST['Breed'] ?? '');
    $gender = sanitizeInput($_POST['Gender'] ?? '');
    $birthday = sanitizeInput($_POST['Birthday'] ?? '');
    $calculated_age = filter_var($_POST['CalculatedAge'] ?? 0, FILTER_VALIDATE_INT);
    $weight = filter_var($_POST['Weight'] ?? '', FILTER_VALIDATE_FLOAT);
    $last_visit = !empty($_POST['LastVisit']) ? sanitizeInput($_POST['LastVisit']) : null;

    if (empty($pet_name)) {
        $errors['Name'] = "Pet name is required.";
    } elseif (strlen(trim($pet_name)) < 2) {
        $errors['Name'] = "Pet name must be at least 2 characters long.";
    } elseif (strlen($pet_name) > 50) {
        $errors['Name'] = "Pet name must not exceed 50 characters.";
    } elseif ($pet_name !== trim($pet_name)) {
        $errors['Name'] = "Pet name should not start or end with spaces.";
    } elseif (preg_match('/\s{2,}/', $pet_name)) {
        $errors['Name'] = "Pet name should not contain consecutive spaces.";
    } elseif (!preg_match('/^[A-Za-z\s\'-]+$/', $pet_name)) {
        $errors['Name'] = "Pet name can only contain letters, spaces, hyphens, and apostrophes.";
    }

    if (empty($species_id) || $species_id <= 0) {
        $errors['SpeciesId'] = "Please select a valid species.";
    }

    if (empty($birthday)) {
        $errors['Birthday'] = "Birthday is required.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        $errors['Birthday'] = "Invalid format. Please use YYYY-MM-DD.";
    } else {
        [$year, $month, $day] = explode('-', $birthday);
        $currentYear = (int) date('Y');

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            $errors['Birthday'] = "Invalid date. Please provide a valid date.";
        } elseif ((int) $year < 1900 || (int) $year > $currentYear) {
            $errors['Birthday'] = "Birth year must be between 1900 and $currentYear.";
        }
    }

    if (empty($weight) || $weight <= 0) {
        $errors['Weight'] = "Weight is required.";
    } elseif ($weight < 0.1 || $weight > 500) {
        $errors['Weight'] = "Weight must be between 0.1 and 500 kg.";
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: add_pet.php?owner_id=$owner_id");
        exit();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Pets WHERE OwnerId = :owner_id AND Name = :name AND SpeciesId = :species_id AND Breed = :breed_id");
    $stmt->execute([
        ':owner_id' => $owner_id,
        ':name' => $pet_name,
        ':species_id' => $species_id,
        ':breed_id' => $breed_id
    ]);
    $existingPetCount = $stmt->fetchColumn();

    if ($existingPetCount > 0) {
        $_SESSION['errors']['duplicate'] = "This pet already exists under this owner.";
        header("Location: record.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO Pets (OwnerId, Name, SpeciesId, Breed, Gender, Birthday, CalculatedAge, Weight, LastVisit)
                VALUES (:OwnerId, :Name, :SpeciesId, :Breed, :Gender, :Birthday, :CalculatedAge, :Weight, :LastVisit)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':OwnerId' => $owner_id,
            ':Name' => $pet_name,
            ':SpeciesId' => $species_id,
            ':Breed' => $breed,
            ':Gender' => $gender,
            ':Birthday' => $birthday,
            ':CalculatedAge' => $calculated_age,
            ':Weight' => $weight,
            ':LastVisit' => $last_visit,
        ]);

        unset($_SESSION['errors'], $_SESSION['form_data']);
        $pdo->commit();

        logActivity(
            $pdo, 
            (int)$_SESSION['UserId'], 
            trim($_SESSION['FirstName'] . ' ' . $_SESSION['LastName']), 
            $_SESSION['Role'], 
            'Add Pet', 
            'Added new pet: ' . htmlspecialchars($pet_name, ENT_QUOTES, 'UTF-8') . 
            ' (Type: ' . htmlspecialchars($species_name, ENT_QUOTES, 'UTF-8') . 
            ', Breed: ' . htmlspecialchars($breed_name, ENT_QUOTES, 'UTF-8') . ')', 
            new DateTime()
        );

        $_SESSION['success'] = "Pet added successfully!";
        header("Location: record.php?success=1");
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding pet: " . $e->getMessage());
        $_SESSION['errors'] = ['database' => "Error adding pet. Please try again."];
        header("Location: add_pet.php?owner_id=$owner_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/register_owner.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }

        .required {
            color: red;
            font-weight: bold;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #198754;
            color: #ffffff;
            border-radius: 8px;
            padding: 10px 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 1050;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9em;
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }

        .toast.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .toast:not(.show) {
            opacity: 0;
            transform: translateY(20px);
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
        </div>
        <nav>
            <h3>Hello, <?= htmlspecialchars($userName) ?></h3>
            <h4><?= htmlspecialchars($role) ?></h4>
            <br>
            <ul class="nav-links">
                <li><a href="main_dashboard.php">
                        <img src="../assets/images/Icons/Chart 1.png" alt="Overview Icon">Overview</a></li>
                <li class="active"><a href="record.php">
                        <img src="../assets/images/Icons/Record 3.png" alt="Record Icon">Record</a></li>
                <li><a href="staff_view.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice and Billing</a></>
            </ul>
        </nav>
        <div class="sidebar-bottom">
            <button onclick="window.location.href='settings.php';">
                <img src="../assets/images/Icons/Settings 1.png" alt="Settings Icon">Settings
            </button>
            <button onclick="window.location.href='../logout/logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>

    <div class="main-content">
        <h1>Add Pet for <?= htmlspecialchars($owner_name); ?></h1>
        <form class="staff-form" action="add_pet.php" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <h2>Pet Information</h2>
            <br>
            <div class="form-row">
                <div class="input-container">
                    <label for="Name">Pet Name:<span class="required-asterisk">*</span></label>
                    <input type="text" id="Name" name="Name" value="<?= htmlspecialchars($form_data['Name'] ?? '') ?>"
                        required minlength="3" aria-invalid="false" placeholder="Enter pet name">
                    <?php if (isset($errors['Name'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['Name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="input-container">
                    <label for="Gender">Gender:</label>
                    <select id="Gender" name="Gender" required>
                        <option value="">Select gender</option>
                        <option value="1" <?= isset($form_data['Gender']) && $form_data['Gender'] === '1' ? 'selected' : '' ?>>Male</option>
                        <option value="2" <?= isset($form_data['Gender']) && $form_data['Gender'] === '2' ? 'selected' : '' ?>>Female</option>
                    </select>
                    <?php if (isset($errors['Gender'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['Gender']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <div class="form-row">
                    <div class="input-container">
                        <label for="SpeciesId">Pet Type:<span class="required-asterisk">*</span></label>
                        <select id="SpeciesId" name="SpeciesId" required>
                            <option value="">Select pet type</option>
                            <?php foreach ($petTypes as $petType): ?>
                                <option value="<?= $petType['Id']; ?>" <?= (isset($form_data['SpeciesId']) && $form_data['SpeciesId'] == $petType['Id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($petType['SpeciesName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['SpeciesId'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['SpeciesId']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="input-container">
                        <label for="Breed">Breed:</label>
                        <select id="Breed" name="Breed">
                            <option value="">Select breed</option>
                            <?php if (isset($form_data['SpeciesId'])): ?>
                                <?php
                                $speciesId = $form_data['SpeciesId'];
                                $stmt = $pdo->prepare("SELECT BreedId, BreedName FROM Breeds WHERE SpeciesId = ?");
                                $stmt->execute([$speciesId]);
                                $breeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php foreach ($breeds as $breed): ?>
                                    <option value="<?= $breed['BreedId']; ?>" <?= (isset($form_data['Breed']) && $form_data['Breed'] == $breed['BreedId']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($breed['BreedName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (isset($errors['Breed'])): ?>
                            <span class="error-message"><?= htmlspecialchars($errors['Breed']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label for="Birthday">Birthday:<span class="required-asterisk">*</span></label>
                    <input type="date" id="Birthday" name="Birthday"
                        value="<?= htmlspecialchars($form_data['Birthday'] ?? '') ?>" required>
                    <?php if (isset($errors['Birthday'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['Birthday']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="input-container">
                    <label for="CalculatedAge">Calculated Age:</label>
                    <input type="text" id="CalculatedAge" name="CalculatedAge" readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label for="Weight">Weight:<span class="required-asterisk">*</span></label>
                    <input type="number" step="0.01" id="Weight" name="Weight"
                        value="<?= htmlspecialchars($form_data['Weight'] ?? '') ?>" placeholder="Enter pet's weight">
                    <?php if (isset($errors['Weight'])): ?>
                        <span class="error-message"><?= htmlspecialchars($errors['Weight']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="input-container">
                    <label for="LastVisit">Date of Last Visit:</label>
                    <input type="date" id="LastVisit" name="LastVisit">
                </div>
            </div>
            <div class="form-buttons">
                <button type="button" class="cancel-btn">Cancel</button>
                <button type="submit" class="regowner-btn">Add Pet</button>
            </div>
        </form>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../assets/js/register_owner.js"></script>
    <script>
        document.getElementById('SpeciesId').addEventListener('change', function () {
            const speciesId = this.value;

            if (speciesId) {
                fetch(`../src/fetch_breeds.php?SpeciesId=${speciesId}`)
                    .then(response => response.json())
                    .then(breeds => {
                        const breedSelect = document.getElementById('Breed');
                        breedSelect.innerHTML = '<option value="">Select breed</option>'; // Reset options

                        // Populate dropdown
                        breeds.forEach(breed => {
                            const option = document.createElement('option');
                            option.value = breed.BreedId; // Use the `BreedId` as value
                            option.textContent = breed.BreedName; // Use `BreedName` for display
                            breedSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching breeds:', error);
                        alert("Unable to fetch breeds. Please try again later.");
                    });
            } else {
                const breedSelect = document.getElementById('Breed');
                breedSelect.innerHTML = '<option value="">Select breed</option>'; // Reset options
            }
        });
    </script>
</body>

</html>