<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../../config/dbh.inc.php';
session_start();

if (!isset($_SESSION['LoggedIn'])) {
    echo "User not logged in.";
    exit;
}

$owner_id = $_SESSION['OwnerId'] ?? null;

if (!$owner_id) {
    echo "Owner ID not found.";
    exit;
}

// Fetch Pets linked to the owner with actual SpeciesName and BreedName
$pets = [];
try {
    $query = "
        SELECT 
            p.PetId, 
            p.Name AS PetName, 
            s.SpeciesName AS PetType, 
            p.Gender, 
            b.BreedName AS Breed
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

// Fetch Consultation Records (Fallback to 'No Records' if empty)
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
                ORDER BY pr.CreatedAt DESC";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
        $stmt->execute();
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive | Record</title>
    <link rel="icon" type="image/x-icon" href="../../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<<<<<<< HEAD
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
=======
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
>>>>>>> cb08ddd97894429e293363256ad40de349aee7c2
    <link rel="stylesheet" href="pet_record.css">
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
                <li><a href="../pet/pet_add.php">Pets</a></li>
                <li><a href="pet_record.php" class="active">Record</a></li>
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
                <h1>Pet Records</h1>
            </div>
        </section>

        <section class="pet-details-section">
            <div class="pet-card">
                <div class="pet-photo">
                    <img src="../../../assets/images/Icons/Profile User.png" alt="Pet Photo">
                </div>

                <div class="pet-name">
                    <select id="pet-dropdown">
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?= $pet['PetId'] ?>" data-species="<?= htmlspecialchars($pet['PetType']) ?>"
                                data-gender="<?= htmlspecialchars($pet['Gender']) ?>"
                                data-breed="<?= htmlspecialchars($pet['Breed']) ?>">
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
                            <h3 class="consultation-title">General Details</h3>
                            <p><b>Date:</b> <?= isset($record['OnsetDate']) ? htmlspecialchars($record['OnsetDate']) : 'N/A' ?></p>
                            <p><b>Chief Complaint:</b> <?= htmlspecialchars($record['ChiefComplaint']) ?></p>
                            <p><b>Duration (Days):</b> <?= htmlspecialchars($record['DurationDays']) ?></p>
                            <p><b>Observed Symptoms:</b> <?= htmlspecialchars($record['ObservedSymptoms']) ?></p>
                            
                            <br>
                            <hr>
                            <br>

                            <h3 class="consultation-title">Medical History</h3>
                            <p><b>Appetite:</b> <?= htmlspecialchars($record['Appetite']) ?></p>
                            <p><b>Diet:</b> <?= htmlspecialchars($record['Diet']) ?></p>
                            <p><b>Medication Prior Checkup:</b> <?= htmlspecialchars($record['MedicationPriorCheckup']) ?></p>
                            
                            <br>
                            <hr>
                            <br>

                            <h3 class="consultation-title">Physical Examination</h3>
                            <p><b>Urine Frequency:</b> <?= htmlspecialchars($record['UrineFrequency']) ?></p>
                            <p><b>Urine Color:</b> <?= htmlspecialchars($record['UrineColor']) ?></p>
                            <p><b>Water Intake:</b> <?= htmlspecialchars($record['WaterIntake']) ?></p>
                            <p><b>Pain Level:</b> <?= htmlspecialchars($record['PainLevel']) ?></p>
                            <p><b>Fecal Score:</b> <?= htmlspecialchars($record['FecalScore']) ?></p>
                            <p><b>Environment:</b> <?= htmlspecialchars($record['Environment']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-consultations-text">No consultation records found.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

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
        document.getElementById("pet-dropdown").addEventListener("change", function() {
            location.href = "pet_record.php?pet_id=" + this.value;
        });
    </script>
    <script>
        document.getElementById("pet-dropdown").addEventListener("change", function () {
            let selectedOption = this.options[this.selectedIndex];

            // Update pet details
            document.getElementById("species").textContent = selectedOption.getAttribute("data-species");
            document.getElementById("gender").textContent = selectedOption.getAttribute("data-gender");
            document.getElementById("breed").textContent = selectedOption.getAttribute("data-breed");

            // Reload consultation records
            let petId = this.value;
            fetch(`../../../src/fetch_pet_records.php?pet_id=${petId}`)
                .then(response => response.json())
                .then(data => {
                    let recordsContainer = document.querySelector(".consultation-container");
                    recordsContainer.innerHTML = ""; // Clear existing records

                    if (data.length > 0) {
                        data.forEach(record => {
                            let recordElement = `
                        <div class="consultation-card">
                            <h3 class="consultation-title">General Details</h3>
                            <p><b>Date:</b> ${record.OnsetDate}</p>
                            <p><b>Chief Complaint:</b> ${record.ChiefComplaint}</p>
                            <p><b>Duration (Days):</b> ${record.DurationDays}</p>
                            <p><b>Observed Symptoms:</b> ${record.ObservedSymptoms}</p>

                            <h3 class="consultation-title">Medical History</h3>
                            <p><b>Appetite:</b> ${record.Appetite}</p>
                            <p><b>Diet:</b> ${record.Diet}</p>
                            <p><b>Medication Prior Checkup:</b> ${record.MedicationPriorCheckup}</p>

                            <h3 class="consultation-title">Physical Examination</h3>
                            <p><b>Urine Frequency:</b> ${record.UrineFrequency}</p>
                            <p><b>Urine Color:</b> ${record.UrineColor}</p>
                            <p><b>Water Intake:</b> ${record.WaterIntake}</p>
                            <p><b>Pain Level:</b> ${record.PainLevel}</p>
                            <p><b>Fecal Score:</b> ${record.FecalScore}</p>
                            <p><b>Environment:</b> ${record.Environment}</p>
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