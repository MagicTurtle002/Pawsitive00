<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* For pets basic info */
/* For pets basic info */
/* For pets basic info */

session_start();

require '../../config/dbh.inc.php';

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: ../owner_login.php');
    exit();
}

$owner_id = $_SESSION['OwnerId']; // Get the logged-in owner's ID
$user_name = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Owner';

$errors = $_SESSION['errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['errors'], $_SESSION['form_data']);
$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

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
    WHERE p.OwnerId = :OwnerId
    ORDER BY p.PetId ASC
    LIMIT :offset, :recordsPerPage";  // Add pagination

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':OwnerId', $owner_id, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch pet types (species) for dropdown
    $speciesStmt = $pdo->query("SELECT Id, SpeciesName FROM Species");
    $petTypes = $speciesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
        header("Location: pet_add.php?owner_id=$owner_id");
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
        header("Location: pet_add.php?owner_id=$owner_id");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM Pets 
        WHERE OwnerId = :owner_id 
        AND LOWER(TRIM(Name)) = LOWER(TRIM(:name)) 
        AND SpeciesId = :species_id 
        AND Breed = :breed
    ");
    $existingPetCount = $stmt->fetchColumn();

    if ($existingPetCount > 0) {
        $_SESSION['errors']['duplicate'] = "This pet already exists under this owner.";
        header("Location: pet_add.php");
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

        $_SESSION['success'] = "Pet added successfully!";
        header("Location: pet_add.php?success=1");
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding pet: " . $e->getMessage());
        $_SESSION['errors'] = ['database' => "Error adding pet. Please try again."];
        header("Location: pet_add.php?owner_id=$owner_id");
        exit();
    }
}
$countQuery = "SELECT COUNT(*) FROM pets WHERE OwnerId = :OwnerId";
$countStmt = $pdo->prepare($countQuery);
$countStmt->bindParam(':OwnerId', $owner_id, PDO::PARAM_INT);
$countStmt->execute();
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive | Pets</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/pet_add.css">

    <script>
        const bookedTimesByDate = <?= json_encode($bookedTimesByDate); ?>;
    </script>
</head>

<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php">
                    <img src="../../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
                </a>
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="book_appointment.php">Appointment</a></li>
                <li><a href="pet_add.php" class="active">Pets</a></li>
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
                <h1>Pets</h1>
            </div>
        </section>
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
                                            <td><?= htmlspecialchars($pet['CalculatedAge'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($pet['Breed']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="no-pets">You have no pets registered.</p>
                    <?php endif; ?>
                </div>
    </main>

    <main>
        <div class="main-content">
            <div class="container">

                <!-- Right Section: Add Pet Form -->
                <div class="right-section">
                    <h2>Add a New Pet</h2>
                    <!-- <h2>Add a New Pet</h2> -->
                    <form class="staff-form" action="pet_add.php" method="POST" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

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
                                <label for="ProfilePicture">Upload Profile Picture:</label>
                                <input type="file" id="ProfilePicture" name="ProfilePicture" accept="image/*" optional>
                            </div>
                            <div class="input-container">
                                <label for="Weight">Weight:<span class="required-asterisk">*</span></label>
                                <input type="number" step="0.01" id="Weight" name="Weight"
                                    value="<?= htmlspecialchars($form_data['Weight'] ?? '') ?>" placeholder="Enter pet's weight">
                                <?php if (isset($errors['Weight'])): ?>
                                    <span class="error-message"><?= htmlspecialchars($errors['Weight']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-buttons">
                            <button type="button" class="cancel-btn"
                                onclick="window.location.href='../index.html'">Cancel</button>
                            <button type="submit" class="regowner-btn">Add Pet</button>
                        </div>
                    </form>
                </div>
    </main>

    <div class="pagination">
        <a href="?page=<?= max(0, $currentPage - 1) ?>">&laquo; Previous</a>
        <?php for ($i = 0; $i < $totalPages; $i++): ?>
            <?php if ($i == 0 || $i == $totalPages - 1 || abs($i - $currentPage) <= 2): ?>
                <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i + 1 ?></a>
            <?php elseif ($i == 1 || $i == $totalPages - 2): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>">Next &raquo;</a>
    </div>

    <br>

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
        document.addEventListener("DOMContentLoaded", function () {
            const dateElement = document.getElementById("current-date");
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const today = new Date().toLocaleDateString('en-US', options);
            dateElement.textContent = today;
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuButtons = document.querySelectorAll('.menu-button');
            menuButtons.forEach(button => {
                button.addEventListener('click', function (event) {
                    const dropdown = this.nextElementSibling;
                    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';

                    document.addEventListener('click', function closeMenu(e) {
                        if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                            dropdown.style.display = 'none';
                            document.removeEventListener('click', closeMenu);
                        }
                    });
                });
            });
        });
    </script>

    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const speciesSelect = document.getElementById('SpeciesId');
            const breedSelect = document.getElementById('Breed');

            speciesSelect.addEventListener('change', function () {
                const speciesId = this.value;
                breedSelect.innerHTML = '<option value="">Select breed</option>'; // Reset dropdown

                if (speciesId) {
                    fetch(`../../src/fetch_breeds.php?SpeciesId=${speciesId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(breeds => {
                            if (breeds.length === 0) {
                                breedSelect.innerHTML = '<option value="">No breeds available</option>';
                            } else {
                                breeds.forEach(breed => {
                                    const option = document.createElement('option');
                                    option.value = breed.BreedId;
                                    option.textContent = breed.BreedName;
                                    breedSelect.appendChild(option);
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching breeds:', error);
                            alert("⚠️ Unable to fetch breeds. Please try again.");
                        });
                }
            });
        });
    </script>

    <script src="../../assets/js/register_owner.js"></script>

</body>

</html>