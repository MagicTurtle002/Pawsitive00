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

    $query = "
        SELECT 
            VaccinationName AS Vaccine, 
            VaccinationDate AS Date, 
            Weight,
            Manufacturer, 
            LotNumber, 
            Notes 
        FROM PetVaccinations 
        WHERE PetId = :PetId
        ORDER BY Date DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':PetId' => $pet_id]);
    $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    </main>

    <main>
        <div class="main-content">
            <div class="container">
                <?php if (isset($_SESSION['message'])): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            Swal.fire({
                                icon: '<?= $_SESSION['message_type'] === "success" ? "success" : "error" ?>',
                                title: '<?= $_SESSION['message'] ?>',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        });
                    </script>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>


                <!-- Right Section: Add Pet Form -->
                <div class="right-section">
                <h2 class="section-headline" style="margin-bottom: 10px;">Consultation Records</h2>
                    <h2 style="color: #156f77;">General Details</h2>
                    <div class="consultation-container">

                    <?php if (!empty($consultations)): ?>
                        <?php foreach ($consultations as $record): ?>  

                            <div class="form-row">
                            <div class="input-container">
                                <label><b>Primary Concern:</b></label>
                                <p><?= !empty($record['ChiefComplaint']) ? nl2br(htmlspecialchars($record['ChiefComplaint'], ENT_QUOTES)) : 'Not Provided' ?></p>

                                <?php if (!empty($_SESSION['errors']['chief_complaint'])): ?>
                                    <span class="error-message" style="color: red;"><?= htmlspecialchars($_SESSION['errors']['chief_complaint']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="input-container">
                                <label><b>Observed Symptoms:</b></label>
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
                        </div>

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
                            <div class="input-container">
                                <label><b>Fecal Score (Bristol):</b></label>
                                <p><?= !empty($record['FecalScore']) ? htmlspecialchars($record['FecalScore'], ENT_QUOTES) : 'Not Provided'; ?></p>
                            </div>

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

                            <div class="input-container">
                            
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-consultations-text">No consultation records found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="main-content">
        <div class="container">
            <div class="right-section">
                <h2 style="color: #156f77;">Vaccines</h2>
                <?php if (!empty($pets)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Weight</th>
                                    <th>Vaccination Name</th>
                                    <th>Date</th>
                                    <th>Manufacturer</th>
                                    <th>Lot Number</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($vaccinations)): ?>
                                    <?php foreach ($vaccinations as $vaccination): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(!empty($vaccination['Weight']) ? $vaccination['Weight'] : 'No information found') ?></td>
                                            <td><?= htmlspecialchars(!empty($vaccination['Vaccine']) ? $vaccination['Vaccine'] : 'No information found') ?></td>
                                            <td><?= htmlspecialchars(!empty($vaccination['Date']) ? $vaccination['Date'] : 'No information found') ?></td>
                                            <td><?= htmlspecialchars(!empty($vaccination['Manufacturer']) ? $vaccination['Manufacturer'] : 'No information found') ?></td>
                                            <td><?= htmlspecialchars(!empty($vaccination['LotNumber']) ? $vaccination['LotNumber'] : 'No information found') ?></td>
                                            <td><?= htmlspecialchars(!empty($vaccination['Notes']) ? $vaccination['Notes'] : 'No information found') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">No vaccination records found for this pet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="pagination">
        <a href="?page=<?= max(0, $currentPage - 1) ?>" class="<?= $currentPage == 0 ? 'disabled' : '' ?>">&laquo; Previous</a>
        
        <?php for ($i = 0; $i < $totalPages; $i++): ?>
            <?php if ($i == 0 || $i == $totalPages - 1 || abs($i - $currentPage) <= 2): ?>
                <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i + 1 ?></a>
            <?php elseif ($i == 1 || $i == $totalPages - 2): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endfor; ?>
        
        <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>" class="<?= $currentPage >= $totalPages - 1 ? 'disabled' : '' ?>">Next &raquo;</a>
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
            const petDropdown = document.getElementById("pet-dropdown");
            const speciesElement = document.getElementById("species");
            const genderElement = document.getElementById("gender");
            const breedElement = document.getElementById("breed");
            const profileImg = document.querySelector(".pet-photo img");
            const recordsContainer = document.querySelector(".consultation-container");

            function updatePetDetails(selectedOption) {
                speciesElement.textContent = selectedOption.getAttribute("data-species");
                genderElement.textContent = selectedOption.getAttribute("data-gender");
                breedElement.textContent = selectedOption.getAttribute("data-breed");
                profileImg.src = selectedOption.getAttribute("data-profile");
            }

            function fetchConsultationRecords(petId) {
                fetch(`../../src/fetch_pet_records.php?pet_id=${petId}`)
                    .then(response => response.json())
                    .then(data => {
                        recordsContainer.innerHTML = ""; // Clear previous records

                        let record = data.length > 0 ? data[0] : {}; // Use first record if exists, else empty object

                        let recordElement = `
                            <div class="form-row">
                                <div class="input-container">
                                    <label><b>Primary Concern:</b></label>
                                    <p>${record.ChiefComplaint || 'Not Provided'}</p>
                                </div>
                                <div class="input-container">
                                    <label><b>Observed Symptoms:</b></label>
                                    <p>${record.ObservedSymptoms || 'Not Provided'}</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label><b>Onset of Symptoms:</b></label>
                                    <p>${record.OnsetDate || 'Not Provided'}</p>
                                </div>
                                <div class="input-container">
                                    <label><b>Duration (Days):</b></label>
                                    <p>${record.DurationDays || 'Not Provided'}</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label><b>Appetite:</b></label>
                                    <p>${record.Appetite || 'Not Provided'}</p>
                                </div>
                                <div class="input-container">
                                    <label><b>Diet:</b></label>
                                    <p>${record.Diet || 'Not Provided'}</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label><b>Urine Frequency:</b></label>
                                    <p>${record.UrineFrequency || 'Not Provided'}</p>
                                </div>
                                <div class="input-container">
                                    <label><b>Urine Color:</b></label>
                                    <p>${record.UrineColor || 'Not Provided'}</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label><b>Water Intake:</b></label>
                                    <p>${record.WaterIntake || 'Not Provided'}</p>
                                </div>
                                <div class="input-container">
                                    <label><b>Environment:</b></label>
                                    <p>${record.Environment || 'Not Provided'}</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label><b>Fecal Score (Bristol):</b></label>
                                    <p>${record.FecalScore || 'Not Provided'}</p>
                                </div>
                                <div class="input-container">
                                    <label><b>Pain Level (1-10):</b></label>
                                    <p>${record.PainLevel || 'Not Provided'}</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <label><b>Medication given prior to check-up:</b></label>
                                    <p>${record.MedicationPriorCheckup || 'Not Provided'}</p>
                                </div>
                            </div>
                        `;

                        recordsContainer.innerHTML = recordElement;
                    })
                    .catch(error => console.error("Error loading records:", error));
            }

            // On pet selection change
            petDropdown.addEventListener("change", function () {
                let selectedOption = this.options[this.selectedIndex];
                let petId = this.value;

                updatePetDetails(selectedOption);
                fetchConsultationRecords(petId);
            });

            // Load first pet’s records on page load
            if (petDropdown.options.length > 0) {
                let firstPet = petDropdown.options[0];
                updatePetDetails(firstPet);
                fetchConsultationRecords(firstPet.value);
            }
        });
    </script>
</body>
</html>