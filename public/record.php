<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

$where_clause = [];
$params = [];

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(
        (Pets.Name LIKE ?)
        OR (Pets.PetCode LIKE ?)
        OR (Owners.FirstName LIKE ?)
        OR (Owners.LastName LIKE ?)
        OR (Species.SpeciesName LIKE ?)
    )";
    $params = array_fill(0, 5, $search); // Ensure exactly 5 placeholders
}

$where_clause[] = "CAST(Pets.IsArchived AS UNSIGNED) = 0 AND CAST(Pets.IsConfined AS UNSIGNED) = 0";

$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
SELECT 
    Owners.OwnerId,
    CONCAT(Owners.FirstName, ' ', Owners.LastName) AS owner_name,
    Owners.Email,
    Owners.Phone,
    Pets.PetId,
    Pets.Status AS PetStatus,
    Pets.Name AS PetName,
    Pets.PetCode,
    Species.SpeciesName AS PetType,

    -- Fetch the last visit (past appointment)
    (SELECT Appointments.AppointmentDate 
        FROM Appointments 
        WHERE Appointments.PetId = Pets.PetId
        AND Appointments.AppointmentDate <= CURDATE()
        AND Appointments.Status IN ('Done', 'Paid')
        ORDER BY Appointments.AppointmentDate DESC, Appointments.AppointmentTime DESC 
        LIMIT 1
    ) AS LastVisit,

    -- Fetch the Service for the Last Visit
    (SELECT Services.ServiceName 
        FROM Appointments 
        INNER JOIN Services ON Appointments.ServiceId = Services.ServiceId
        WHERE Appointments.PetId = Pets.PetId
        AND Appointments.AppointmentDate <= CURDATE()
        AND Appointments.Status IN ('Done', 'Paid')
        ORDER BY Appointments.AppointmentDate DESC, Appointments.AppointmentTime DESC 
        LIMIT 1
    ) AS LastVisitService,

    -- Fetch the Appointment Time for the Last Visit
    (SELECT Appointments.AppointmentTime 
        FROM Appointments 
        WHERE Appointments.PetId = Pets.PetId
        AND Appointments.AppointmentDate <= CURDATE()
        AND Appointments.Status IN ('Done', 'Paid')
        ORDER BY Appointments.AppointmentDate DESC, Appointments.AppointmentTime DESC 
        LIMIT 1
    ) AS LastVisitTime,

        -- Fetch next appointment details
    (SELECT Appointments.AppointmentDate 
        FROM Appointments 
        WHERE Appointments.PetId = Pets.PetId
        AND Appointments.AppointmentDate >= CURDATE()
        ORDER BY Appointments.AppointmentDate ASC, Appointments.AppointmentTime ASC 
        LIMIT 1
    ) AS NextAppointment,

    -- Fetch the next follow-up if available
    (SELECT FollowUpDate 
        FROM FollowUps 
        WHERE FollowUps.RecordId = Pets.PetId 
        ORDER BY FollowUpDate ASC 
        LIMIT 1
    ) AS NextFollowUp,

    -- Fetch Service for the Next Appointment
    (SELECT Services.ServiceName 
        FROM Appointments 
        INNER JOIN Services ON Appointments.ServiceId = Services.ServiceId
        WHERE Appointments.PetId = Pets.PetId
        AND Appointments.AppointmentDate >= CURDATE()
        ORDER BY Appointments.AppointmentDate ASC, Appointments.AppointmentTime ASC 
        LIMIT 1
    ) AS NextServiceName,

    -- Fetch Appointment Time for the Next Appointment
    (SELECT Appointments.AppointmentTime 
        FROM Appointments 
        WHERE Appointments.PetId = Pets.PetId
        AND Appointments.AppointmentDate >= CURDATE()
        ORDER BY Appointments.AppointmentDate ASC, Appointments.AppointmentTime ASC 
        LIMIT 1
    ) AS NextAppointmentTime,

    -- Combined Next Visit: Prioritizing Follow-Up, then Appointment
    COALESCE(
        (SELECT FollowUpDate 
            FROM FollowUps 
            WHERE FollowUps.RecordId = Pets.PetId 
            ORDER BY FollowUpDate ASC 
            LIMIT 1),
        (SELECT Appointments.AppointmentDate 
            FROM Appointments 
            WHERE Appointments.PetId = Pets.PetId
            AND Appointments.AppointmentDate >= CURDATE()
            ORDER BY Appointments.AppointmentDate ASC, Appointments.AppointmentTime ASC 
            LIMIT 1)
    ) AS NextVisit

FROM Pets
INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
LEFT JOIN Species ON Pets.SpeciesId = Species.Id
WHERE Pets.IsArchived = 0 AND Pets.IsConfined = 0
ORDER BY NextVisit DESC
LIMIT ?, ?;
";
$params[] = $offset;
$params[] = $recordsPerPage;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "
    SELECT COUNT(DISTINCT Pets.PetId) 
    FROM Pets
    INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Species ON Pets.SpeciesId = Species.Id
    $where_sql
";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute(array_slice($params, 0, -2));
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/vet_record.css">
    <style>
        .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .success-toast {
            background-color: #198754;
            /* Success green */
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: fade-in 0.5s ease, fade-out 0.5s ease 3s;
            opacity: 1;
        }

        .success-toast i {
            font-size: 16px;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fade-out {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(20px);
            }
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
                <li><a href="staff.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice</a></li>
            </ul>
        </nav>
        <div class="sidebar-bottom">
            <button onclick="window.location.href='settings.php';">
                <img src="../assets/images/Icons/Settings 1.png" alt="Settings Icon">Settings
            </button>
            <button onclick="window.location.href='logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Record</h1>
            <div class="actions">
                <form method="GET" action="record.php" class="filter-container">
                    <input type="text" id="searchInput" name="search" placeholder="Search record..." autocomplete="off"
                        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                    <div class="dropdown-filter">
                        <button type="button" class="filter-btn">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <div class="dropdown-content">
                            <label>
                                <input type="radio" name="filter[]" value="pet_name"> Pet Name
                            </label>
                            <label>
                                <input type="radio" name="filter[]" value="pet_service"> Services
                            </label>
                            <label>
                                <input type="radio" name="filter[]" value="owner_name"> Owner Name
                            </label>
                            <hr>
                            <label>
                                <input type="radio" name="order" value="asc"> Ascending
                            </label>
                            <label>
                                <input type="radio" name="order" value="desc"> Descending
                            </label>
                            <hr>
                            <button type="submit" class="apply-btn">Apply Filter</button>
                            <button type="button" class="clear-btn" onclick="resetFilters()">Clear Filter</button>
                        </div>
                    </div>
                </form>

                <div class="button-group">
                    <button class="add-btn" onclick="location.href='confined_pets.php'">Confined Pet</button>
                    <button class="add-btn" onclick="location.href='archive_list.php'">Archive</button>
                    <button class="add-btn" onclick="location.href='add_owner_pet.php'">+ Add Owner and Pet</button>
                </div>
            </div>
        </div>

        <table class="staff-table">
            <thead>
                <tr>
                    <th>Pet ID</th>
                    <th>Pet Name</th>
                    <th>Pet Type</th>
                    <th>Status</th>
                    <th>Last Visit</th>
                    <th>Next Visit</th>
                </tr>
            </thead>
            <tbody id="staffList">
                <?php if (count($pets) > 0): ?>
                    <?php foreach ($pets as $pet): ?>
                        <tr class="pet-row" onclick="togglePetDetails(this)">
                            <td>
                                <div class="hover-container">
                                    <?= htmlspecialchars($pet['PetCode'] ?? 'No information found') ?>
                                    <i class="fas fa-info-circle"></i>
                                    <div class="hover-card">
                                        <div class="profile-info">
                                            <img src="../assets/images/Icons/Profile User.png" alt="Profile Pic"
                                                class="profile-img" width="10px">
                                            <div>
                                                <strong><?= htmlspecialchars($pet['owner_name']) ?></strong><br>
                                                <?= htmlspecialchars($pet['role'] ?? 'Owner') ?>
                                            </div>
                                        </div>
                                        <hr>
                                        <div>
                                            <strong>Authorized Representative:</strong><br>
                                            <?= htmlspecialchars($pet['Authorized Representative'] ?? 'No information found') ?>
                                        </div>
                                        <br>
                                        <div>
                                            <strong>Email:</strong><br>
                                            <?= htmlspecialchars($pet['Email']) ?>
                                        </div>
                                        <br>
                                        <div>
                                            <strong>Phone Number:</strong><br>
                                            <?= htmlspecialchars($pet['Phone']) ?>
                                        </div>
                                        <hr>
                                        <div style="text-align: center; margin-top: 10px;">
                                            <a href="add_pet.php?owner_id=<?= htmlspecialchars($pet['OwnerId']) ?>"
                                                class="add-pet-button">
                                                + Add Pet
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="three-dot-menu" style="display: inline-block;">
                                    <button class="three-dot-btns">⋮</button>
                                    <div class="dropdown-menus" style="display: none;">
                                        <a href="#" onclick="confirmConfine('<?= htmlspecialchars($pet['PetId']) ?>')">Confine
                                            Pet</a>
                                        <a href="add_vaccine.php?pet_id=<?= htmlspecialchars($pet['PetId']) ?>">Add
                                            Vaccination</a>
                                        <a href="#"
                                            onclick="confirmArchive('<?= htmlspecialchars($pet['PetId']) ?>'); return false;">Archive
                                            Pet</a>
                                        <a href="#"
                                            onclick="confirmDelete('<?= htmlspecialchars($pet['PetId']) ?>'); return false;">Delete</a>
                                    </div>
                                </div>

                                <a href="pet_profile.php?pet_id=<?= htmlspecialchars($pet['PetId'] ?? 'Unknown') ?>"
                                    class="pet-profile-link">
                                    <?= htmlspecialchars($pet['PetName'] ?? 'No Name') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($pet['PetType'] ?? 'No information found') ?></td>
                            <td><?= htmlspecialchars($pet['PetStatus'] ?? 'Unknown') ?></td>
                            <td>
                                <div class="hover-container">
                                    <span
                                        class="last-visit"><?= htmlspecialchars($pet['LastVisit'] ?? 'No past visit') ?></span>
                                    <div class="hover-card">
                                        <strong>Last Visit Details:</strong><br>
                                        <strong>Date:</strong> <?= htmlspecialchars($pet['LastVisit'] ?? 'No past visit') ?><br>
                                        <strong>Service:</strong>
                                        <?= htmlspecialchars($pet['LastVisitService'] ?? 'No details') ?><br>
                                        <strong>Time:</strong>
                                        <?= htmlspecialchars($pet['LastVisitTime'] ?? 'No time set') ?><br>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="hover-container">
                                    <span
                                        class="next-visit"><?= htmlspecialchars($pet['NextVisit'] ?? 'No upcoming visit') ?></span>
                                    <div class="hover-card">
                                        <strong>Next Visit Details:</strong><br>
                                        <span>
                                            <?= $pet['NextFollowUp'] ? 'Follow-Up' : 'Appointment' ?>
                                        </span><br>
                                        <strong>Date:</strong>
                                        <?= htmlspecialchars($pet['NextVisit'] ?? 'No upcoming visit') ?><br>
                                        <?php if ($pet['NextFollowUp']): ?>
                                            <strong>Follow-Up Notes:</strong>
                                            <?= htmlspecialchars($pet['FollowUpNotes'] ?? 'No details') ?><br>
                                        <?php else: ?>
                                            <strong>Service:</strong>
                                            <?= htmlspecialchars($pet['ServiceName'] ?? 'No details') ?><br>
                                            <strong>Time:</strong>
                                            <?= htmlspecialchars($pet['AppointmentTime'] ?? 'No time set') ?><br>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="dropdown-row" style="display: none;">
                            <td colspan="6">
                                <div class="dropdown-wrapper">
                                    <label for="pets-dropdown-<?= $pet['OwnerId'] ?>">All pets:</label>
                                    <select id="pets-dropdown-<?= $pet['OwnerId'] ?>-<?= $pet['PetId'] ?>"
                                        class="pets-dropdown">
                                        <option value="">Select a pet</option>
                                        <?php
                                        $allPetsQuery = $pdo->prepare("SELECT PetId, Name FROM Pets WHERE OwnerId = ?");
                                        $allPetsQuery->execute([$pet['OwnerId']]);
                                        $allPets = $allPetsQuery->fetchAll(PDO::FETCH_ASSOC);

                                        if (!empty($allPets)):
                                            foreach ($allPets as $currentPet): ?>
                                                <option value="<?= htmlspecialchars($currentPet['PetId']) ?>">
                                                    <?= htmlspecialchars($currentPet['Name']) ?>
                                                </option>
                                            <?php endforeach;
                                        else: ?>
                                            <option value="">No pets found</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination">
            <a href="?page=<?= max(0, $currentPage - 1) ?>">&laquo; Previous</a>
            <?php for ($i = 0; $i < $totalPages; $i++): ?>
                <?php if ($i == 0 || $i == $totalPages - 1 || abs($i - $currentPage) <= 2): ?>
                    <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i + 1 ?></a>
                <?php elseif ($i == 1 || $i == $totalPages - 2): ?>
                    <span style="display: inline-block; margin-top: 15px;">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>">Next &raquo;</a>
        </div>
        <?php if (isset($_SESSION['errors']['duplicate'])): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    Swal.fire({
                        title: "Duplicate Entry",
                        text: "<?= htmlspecialchars($_SESSION['errors']['duplicate']) ?>",
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                });
            </script>
            <?php unset($_SESSION['errors']['duplicate']); // Clear error after displaying ?>
        <?php endif; ?>
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    Swal.fire({
                        title: "Success!",
                        text: "Pet added successfully!",
                        icon: "success",
                        confirmButtonText: "OK"
                    });
                });
            </script>
        <?php endif; ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                document.querySelector(".apply-btn").addEventListener("click", function (event) {
                    event.preventDefault();
                    loadFilteredPets();
                });

                document.querySelector(".clear-btn").addEventListener("click", function () {
                    resetFilters();
                });

                // Initialize the three-dot menu on page load
                initializeThreeDotMenu();
            });

            // Function to fetch filtered pets and retain styles & interactions
            function loadFilteredPets() {
                const formData = new FormData(document.querySelector(".filter-container"));
                const queryString = new URLSearchParams(formData).toString();

                fetch(`../src/filter_pets.php?${queryString}`)
                    .then(response => response.json())
                    .then(data => {
                        const tableBody = document.getElementById("staffList");
                        tableBody.innerHTML = "";

                        if (data.length === 0) {
                            tableBody.innerHTML = "<tr><td colspan='6'>No pets found.</td></tr>";
                            return;
                        }

                        data.forEach(pet => {
                            const row = `<tr class="pet-row">
                    <td>
                        <div class="hover-container">
                            ${pet.PetCode ?? 'No information'}
                            <i class="fas fa-info-circle"></i>
                            <div class="hover-card">
                                <div class="profile-info">
                                    <img src="../assets/images/Icons/Profile User.png" class="profile-img" width="10px">
                                    <div>
                                        <strong>${pet.OwnerName}</strong><br>
                                        <span class="role">Authorized Representative</span>
                                    </div>
                                </div>
                                <hr>
                                <div><strong>Email:</strong><br> ${pet.Email}</div><br>
                                <div><strong>Phone Number:</strong><br> ${pet.Phone}</div>
                                <hr>
                                <div style="text-align: center; margin-top: 10px;">
                                    <a href="add_pet.php?owner_id=${pet.OwnerId}" class="add-pet-button">+ Add Pet</a>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="three-dot-menu">
                            <button class="three-dot-btns">⋮</button>
                            <div class="dropdown-menus">
                                <a href="#" onclick="confirmConfine('${pet.PetId}')">Confine Pet</a>
                                <a href="add_vaccine.php?pet_id=${pet.PetId}">Add Vaccination</a>
                                <a href="#" onclick="confirmArchive('${pet.PetId}'); return false;">Archive Pet</a>
                                <a href="#" onclick="confirmDelete('${pet.PetId}'); return false;">Delete</a>
                            </div>
                        </div>
                        <a href="pet_profile.php?pet_id=${pet.PetId}" class="pet-profile-link">${pet.PetName ?? 'No Name'}</a>
                    </td>
                    <td>${pet.PetType ?? 'No information'}</td>
                    <td>${pet.PetStatus ?? 'Unknown'}</td>
                    <td>${pet.LastVisit ?? 'No past visit'}</td>
                    <td>${pet.NextVisit ?? 'No upcoming visit'}</td>
                </tr>`;
                            tableBody.innerHTML += row;
                        });

                        initializeThreeDotMenu(); // Reinitialize event listeners after updating table
                    })
                    .catch(error => console.error("Error fetching pets:", error));
            }

            function resetFilters() {
                document.querySelector(".filter-container").reset();
                loadFilteredPets();
            }

            // Function to handle three-dot button clicks dynamically
            function initializeThreeDotMenu() {
                document.querySelectorAll(".three-dot-btns").forEach(button => {
                    button.addEventListener("click", function (event) {
                        event.stopPropagation(); // Prevents clicking outside from closing immediately
                        closeOtherDropdowns();
                        const menu = this.nextElementSibling;
                        menu.style.display = menu.style.display === "block" ? "none" : "block";
                    });
                });

                // Close dropdown when clicking outside
                document.addEventListener("click", function () {
                    closeOtherDropdowns();
                });
            }

            // Function to close all open dropdowns
            function closeOtherDropdowns() {
                document.querySelectorAll(".dropdown-menus").forEach(menu => {
                    menu.style.display = "none";
                });
            }
        </script>
        <script>
            function confirmDelete(petId) {
                if (!petId || isNaN(petId)) {
                    Swal.fire("Error!", "Invalid Pet ID.", "error");
                    return;
                }

                Swal.fire({
                    title: "Are you sure?",
                    text: "This action will permanently delete the pet record.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    cancelButtonColor: "#3085d6",
                    confirmButtonText: "Yes, delete it!",
                    cancelButtonText: "Cancel"
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log("Sending delete request for Pet ID:", petId);
                        fetch("../src/delete_pet.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ pet_id: petId })
                        })
                            .then(response => response.json())
                            .then(data => {
                                console.log("Response from server:", data);
                                if (data.success) {
                                    Swal.fire("Deleted!", "The pet record has been deleted.", "success")
                                        .then(() => location.reload());
                                } else {
                                    Swal.fire("Error!", data.error || "Failed to delete the pet.", "error");
                                }
                            })
                            .catch(error => {
                                console.error("Error deleting pet:", error);
                                Swal.fire("Error!", "An error occurred while deleting the pet.", "error");
                            });
                    }
                });
            }
        </script>
        <script src="../assets/js/record.js?v=<?= time(); ?>"></script>
</body>

</html>