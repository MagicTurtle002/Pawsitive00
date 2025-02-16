<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../config/dbh.inc.php';
session_start();

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: ../owner_login.php');
    exit();
}

$owner_id = $_SESSION['OwnerId'] ?? null;

$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 5; // You can adjust this value
$offset = $currentPage * $recordsPerPage;

if (!$owner_id) {
    echo "Owner ID not found.";
    exit;
}

$pets = [];
try {
    $query = "
        SELECT 
            p.PetId, 
            p.Name AS PetName, 
            s.SpeciesName AS PetType, 
            p.Gender, 
            b.BreedName AS Breed,
            p.ProfilePicture
        FROM Pets p
        JOIN Species s ON p.SpeciesId = s.Id
        JOIN Breeds b ON p.Breed = b.BreedId
        WHERE p.OwnerId = :owner_id
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

$pet = $pets[0] ?? null;

$consultations = [];
if (!empty($pets)) {
    try {
        $query = "SELECT 
            pr.RecordId, pr.PetId, pr.AppointmentId, pr.ChiefComplaint, pr.OnsetDate, pr.DurationDays, 
            pr.ObservedSymptoms, pr.Appetite, pr.Diet, pr.UrineFrequency, pr.UrineColor, 
            pr.WaterIntake, pr.PainLevel, pr.FecalScore, pr.Environment, pr.MedicationPriorCheckup, 
            pr.CreatedAt, pr.UpdatedAt
        FROM PatientRecords pr
        WHERE pr.PetId IN (SELECT PetId FROM Pets WHERE OwnerId = :owner_id) 
        ORDER BY pr.CreatedAt DESC
        LIMIT :offset, :recordsPerPage";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
    }
}

$countQuery = "SELECT COUNT(*) FROM PatientRecords WHERE PetId IN (SELECT PetId FROM Pets WHERE OwnerId = :owner_id)";
$countStmt = $pdo->prepare($countQuery);
$countStmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
$countStmt->execute();
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive | Record</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/pet_record.css">
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
                <li><a href="pet_add.php">Pets</a></li>
                <li><a href="pet_record.php" class="active">Record</a></li>
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
                <h1>Pet Records</h1>
            </div>
        </section>

        <section class="pet-details-section">
            <div class="pet-card">
                <div class="pet-photo">
                    <img src="<?= !empty($pet['ProfilePicture']) && file_exists("../../uploads/pet_avatars/" . $pet['ProfilePicture'])
                        ? "../../uploads/pet_avatars/" . htmlspecialchars($pet['ProfilePicture'])
                        : '../../assets/images/Icons/Profile User.png'; ?>"
                        alt="<?= htmlspecialchars($pet['PetName']); ?>'s Profile Picture"
                        style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%;">
                </div>

                <div class="pet-name">
                    <select id="pet-dropdown">
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?= $pet['PetId'] ?>"
                                data-species="<?= htmlspecialchars($pet['PetType']) ?>"
                                data-gender="<?= htmlspecialchars($pet['Gender']) ?>"
                                data-breed="<?= htmlspecialchars($pet['Breed']) ?>"
                                data-profile="<?= !empty($pet['ProfilePicture']) && file_exists("../../uploads/pet_avatars/" . $pet['ProfilePicture'])
                                    ? "../../uploads/pet_avatars/" . htmlspecialchars($pet['ProfilePicture'])
                                    : '../../assets/images/Icons/Profile User.png'; ?>">
                                <?= htmlspecialchars($pet['PetName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pet-info">
                    <div class="pet-info-item">
                        <p class="label">Pet Type</p>
                        <p class="value" id="species"><?= htmlspecialchars($pets[0]['PetType'] ?? 'N/A') ?></p>
                    </div>
                    <div class="pet-info-item">
                        <p class="label">Gender</p>
                        <p class="value" id="gender"><?= htmlspecialchars($pets[0]['Gender'] ?? 'N/A') ?></p>
                    </div>
                    <div class="pet-info-item">
                        <p class="label">Breed</p>
                        <p class="value" id="breed"><?= htmlspecialchars($pets[0]['Breed'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
        </section>

        <hr style="width: 50%; margin: 0 auto;">

        <section class="consultation-section">
            <h2 class="section-headline">Consultation Records</h2>
            <div class="consultation-container">
                <?php if (!empty($consultations)): ?>
                    <?php foreach ($consultations as $record): ?>
                        <div class="consultation-card">
                        <h2>General Details</h2>
            <div class="form-row">
                <div class="input-container">
                    <label><b>Primary Concern:</b></label>
                    <p><?= !empty($record['ChiefComplaint']) ? nl2br(htmlspecialchars($record['ChiefComplaint'], ENT_QUOTES)) : 'Not Provided' ?></p>

                    <?php if (!empty($_SESSION['errors']['chief_complaint'])): ?>
                        <span class="error-message" style="color: red;"><?= htmlspecialchars($_SESSION['errors']['chief_complaint']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="input-container">
                    <label><b>Observed Symptoms:</b></label>
                    <div style="display: flex; flex-wrap: wrap; gap: 46.9px;">
                        <?php
                        $symptomsList = ['Vomiting', 'Diarrhea', 'Lethargy', 'Coughing', 'Sneezing', 'Other'];
                        $hasSymptoms = !empty($form_data['symptoms']); // Check if symptoms exist

                        if ($hasSymptoms) {
                            foreach ($symptomsList as $symptom):
                                $symptomKey = strtolower(str_replace(' ', '_', $symptom)); // Convert to lowercase for input naming
                                $isChecked = in_array($symptom, $form_data['symptoms'] ?? []);
                                $details = htmlspecialchars($form_data[$symptomKey . '-detail'] ?? '', ENT_QUOTES);
                        ?>
                                <label>
                                    <input type="checkbox" name="symptoms[]" value="<?= $symptom ?>" 
                                        <?= $isChecked ? 'checked' : ''; ?>
                                        onclick="toggleSymptomDetail(this, '<?= $symptomKey ?>')">
                                    <?= $symptom ?>
                                </label>
                                <input type="text" id="<?= $symptomKey ?>-detail" name="<?= $symptomKey ?>-detail" 
                                    placeholder="Specify details for <?= $symptom ?>"
                                    value="<?= $details ?>"
                                    style="display: <?= !empty($details) ? 'block' : 'none'; ?>;">
                        <?php endforeach;
                        } else {
                            echo '<p style="color: red;">Not Provided</p>';
                        }
                        ?>
                    </div>
                <br>

                <div class="form-row">
                    <div class="input-container">
                        <label><b>Onset of Symptoms:</b></label>
                        <p><?= !empty($record['OnsetDate']) ? htmlspecialchars($record['OnsetDate'], ENT_QUOTES) : 'Not Provided' ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Duration (Days):</b></label>
                        <p>
                            <?php 
                            $durationMapping = [
                                '1' => '1 Day', '2' => '2 Days', '3' => '3 Days', '5' => '5 Days', 
                                '7' => '1 Week', '14' => '2 Weeks', '30' => '1 Month'
                            ];
                            echo isset($record['DurationDays']) ? ($durationMapping[$record['DurationDays']] ?? 'Not Provided') : 'Not Provided';
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-container">
                        <label><b>Appetite:</b></label>
                        <p><?= !empty($record['Appetite']) ? htmlspecialchars($record['Appetite'], ENT_QUOTES) : 'Not Provided' ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Diet:</b></label>
                        <p><?= !empty($record['Diet']) ? htmlspecialchars($record['Diet'], ENT_QUOTES) : 'Not Provided' ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label><b>Urine Frequency:</b></label>
                        <p><?= !empty($record['UrineFrequency']) ? htmlspecialchars($record['UrineFrequency'], ENT_QUOTES) : 'Not Provided' ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Urine Color:</b></label>
                        <p><?= !empty($record['UrineColor']) ? htmlspecialchars($record['UrineColor'], ENT_QUOTES) : 'Not Provided' ?></p>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-container">
                        <label><b>Water Intake:</b></label>
                        <p><?= !empty($record['WaterIntake']) ? htmlspecialchars($record['WaterIntake'], ENT_QUOTES) : 'Not Provided' ?></p>
                    </div>

                    <div class="input-container">
                        <label><b>Environment:</b></label>
                        <p><?= !empty($record['Environment']) ? htmlspecialchars($record['Environment'], ENT_QUOTES) : 'Not Provided' ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Fecal Score -->
                    <div class="input-container" style="display: flex; flex-direction: column; align-items: flex-start; gap: 8px; width: 100%;">
                        <label><b>Fecal Score (Bristol):</b></label>
                        <p><?= !empty($record['FecalScore']) ? htmlspecialchars($record['FecalScore'], ENT_QUOTES) : 'Not Provided'; ?></p>
                    </div>

                    <!-- Pain Level -->
                    <div class="input-container">
                        <label><b>Pain Level (1-10):</b></label>
                        <p><?= htmlspecialchars($record['PainLevel'] ?? 'Not Provided', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <label><b>Medication given prior to check-up:</b></label>
                        <p><?= !empty($record['MedicationPriorCheckup']) 
                                ? htmlspecialchars($record['MedicationPriorCheckup'], ENT_QUOTES, 'UTF-8') 
                                : 'No medication recorded'; ?>
                        </p>
                    </div>
                </div>   
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-consultations-text">No consultation records found.</p>
                <?php endif; ?>
            </div>
        </section>
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
    document.getElementById("pet-dropdown").addEventListener("change", function () {
        let selectedOption = this.options[this.selectedIndex];

        // ✅ Update pet details dynamically
        document.getElementById("species").textContent = selectedOption.getAttribute("data-species");
        document.getElementById("gender").textContent = selectedOption.getAttribute("data-gender");
        document.getElementById("breed").textContent = selectedOption.getAttribute("data-breed");

        // ✅ Update profile picture dynamically
        document.querySelector(".pet-photo img").src = selectedOption.getAttribute("data-profile");

        // ✅ Fetch and update consultation records dynamically
        let petId = this.value;
        fetch(`../../src/fetch_pet_records.php?pet_id=${petId}`)
            .then(response => response.json())
            .then(data => {
                let recordsContainer = document.querySelector(".consultation-container");
                recordsContainer.innerHTML = ""; // Clear previous records

                if (data.length > 0) {
                    data.forEach(record => {
                        let recordElement = `
                            <div class="consultation-card">
                                <h3 class="consultation-title">General Details</h3>
                                <p><b>Date:</b> ${record.OnsetDate || 'N/A'}</p>
                                <p><b>Primary Concern:</b> ${record.ChiefComplaint || 'N/A'}</p>
                                <p><b>Observed Symptoms:</b> ${record.ObservedSymptoms || 'N/A'}</p>
                                <p><b>Duration (Days):</b> ${record.DurationDays || 'N/A'}</p>
                                <p><b>Appetite:</b> ${record.Appetite || 'N/A'}</p>
                                <p><b>Diet:</b> ${record.Diet || 'N/A'}</p>
                                <p><b>Urine Frequency:</b> ${record.UrineFrequency || 'N/A'}</p>
                                <p><b>Urine Color:</b> ${record.UrineColor || 'N/A'}</p>
                                <p><b>Water Intake:</b> ${record.WaterIntake || 'N/A'}</p>
                                <p><b>Environment:</b> ${record.Environment || 'N/A'}</p>
                                <p><b>Fecal Score:</b> ${record.FecalScore || 'N/A'}</p>
                                <p><b>Pain Level:</b> ${record.PainLevel || 'N/A'}</p>
                                <p><b>Medication Prior to Check Up:</b> ${record.MedicationPriorCheckup || 'N/A'}</p>
                            </div>
                        `;
                        recordsContainer.innerHTML += recordElement;
                    });
                } else {
                    recordsContainer.innerHTML = "<p class='no-consultations-text'>No consultation records found.</p>";
                }
            })
            .catch(error => console.error("Error loading records:", error));
    });
    </script>
</body>

</html>