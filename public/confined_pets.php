<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
ob_start();

require_once __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/log_helpers.php';

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: staff_login.php');
    exit;
}

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

$where_clause = ["Pets.IsConfined = 1"];
$params = [];

// ✅ Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(LOWER(Pets.Name) LIKE LOWER(?) OR LOWER(Pets.PetCode) LIKE LOWER(?))";
    array_push($params, $search, $search);
}

// ✅ Date range filter
if (!empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $where_clause[] = "Pets.LastVisit BETWEEN ? AND ?";
    array_push($params, $_GET['startDate'], $_GET['endDate']);
}

// ✅ Sorting filter (Ascending or Descending)
$orderBy = "ORDER BY Pets.LastVisit DESC";
if (!empty($_GET['order']) && $_GET['order'] === 'asc') {
    $orderBy = "ORDER BY Pets.LastVisit ASC";
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clause);

$query = "
    SELECT 
        Pets.LastVisit AS ConfinementDate,
        Owners.OwnerId,
        CONCAT(Owners.FirstName, ' ', Owners.LastName) AS OwnerName,
        Owners.Email,
        Owners.Phone,
        Pets.PetId,
        Pets.PetCode,
        Pets.Name AS PetName,
        Species.SpeciesName AS PetType,
        Pets.Breed,
        Pets.Gender,
        Pets.Birthday,
        Pets.Weight
    FROM Pets
    LEFT JOIN Owners ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Species ON Pets.SpeciesId = Species.Id

    $where_sql
    ORDER BY Pets.LastVisit DESC
    LIMIT ?, ?
";

array_push($params, $offset, $recordsPerPage);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$confinedPets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentDate = null; // Variable to track the current date

$countQuery = "
    SELECT COUNT(*) 
    FROM Pets
    LEFT JOIN Owners ON Pets.OwnerId = Owners.OwnerId
    $where_sql
";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute(array_slice($params, 0, -2)); // Remove LIMIT params
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
            <h1>Confined Pets</h1>
            <div class="actions">
                <form id="filterForm" class="filter-container">
                    <!-- Search Input -->
                    <input type="text" id="searchInput" name="search" placeholder="Search confined pets..."
                        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        oninput="applyFilters()">

                    <!-- Filter Dropdown -->
                    <div class="dropdown-filter">
                        <button type="button" class="filter-btn">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <div class="dropdown-content">
                            <!-- Date Range Filters -->
                            <label>Date Range:</label>
                            <input type="date" id="startDate" name="startDate" onchange="applyFilters()">
                            <input type="date" id="endDate" name="endDate" onchange="applyFilters()">

                            <hr>

                            <!-- Sort Order -->
                            <label>Sort By:</label>
                            <select id="sortOrder" name="order" onchange="applyFilters()">
                                <option value="desc">Newest First</option>
                                <option value="asc">Oldest First</option>
                            </select>

                            <hr>


                            <button type="button" class="apply-btn" onclick="applyFilters()">Apply Filter</button>
                            <button type="button" class="clear-btn" onclick="clearFilters()">Clear Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <table class="staff-table">
            <thead>
                <tr>
                    <th>Pet Code</th>
                    <th>Pet Name</th>
                    <th>Pet Type</th>
                    <th>Weight</th>
                    <th>Date of Confinement</th>
                    <th>Day</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="confinedPetsList">
                <?php if (count($confinedPets) > 0): ?>
                    <?php
                    $currentDate = null;
                    foreach ($confinedPets as $pet):
                        $confinedDate = !empty($pet['ConfinementDate']) ? date("F j, Y", strtotime($pet['ConfinementDate'])) : 'N/A';

                        // ✅ Update only the existing header (don't create a new <thead>)
                        if ($currentDate !== $confinedDate):
                            $currentDate = $confinedDate;
                            echo "<tr><th colspan='7' class='date-header'>$confinedDate</th></tr>";
                        endif;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($pet['PetCode']) ?></td>
                            <td>
                                <a href="pet_profile.php?pet_id=<?= htmlspecialchars($pet['PetId']) ?>">
                                    <?= htmlspecialchars($pet['PetName']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($pet['PetType']) ?></td>
                            <td><?= htmlspecialchars($pet['Weight']) ?> kg</td>
                            <td><?= htmlspecialchars($pet['ConfinementDate'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($pet['Day'] ?? 'N/A') ?></td>
                            <td>
                                <a href="#" class="action-btn add-report-btn"
                                    onclick="addConfinementReport('<?= htmlspecialchars($pet['PetId']) ?>'); return false;">
                                    Add Report
                                </a>
                                <a href="#" class="action-btn release-btn"
                                    onclick="releasePet('<?= htmlspecialchars($pet['PetId']) ?>'); return false;">
                                    Release
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No confined pets found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div id="toastContainer" class="toast-container">
            <?php if (!empty($_SESSION['success'])): ?>
                <div class="success-toast" id="successToast">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); // Clear the message after displaying ?>
            <?php endif; ?>
        </div>
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
        <script>
            function toggleDropdown(event, btn) {
                event.stopPropagation(); // Prevents closing immediately when clicking button

                let dropdown = btn.nextElementSibling;
                document.querySelectorAll(".dropdown-menus").forEach(menu => {
                    if (menu !== dropdown) menu.style.display = "none"; // Close other open menus
                });

                dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
            }

            window.addEventListener("click", function () {
                document.querySelectorAll(".dropdown-menus").forEach(menu => {
                    menu.style.display = "none";
                });
            });
        </script>
        <script>
            function releasePet(petId) {
                Swal.fire({
                    title: "Are you sure?",
                    text: "This pet will be marked as released and moved back to records.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, release it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch("../src/release_pet.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: `petId=${petId}`
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire("Released!", "The pet has been successfully released.", "success");

                                    // ✅ Remove the row without reloading the page
                                    const row = document.querySelector(`#pet-row-${petId}`);
                                    if (row) row.remove();

                                    // ✅ If no pets remain, show "No confined pets found."
                                    const tableBody = document.getElementById("confinedPetsList");
                                    if (tableBody.children.length === 0) {
                                        tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; font-weight: bold; color: red;">
                            No confined pets found.
                        </td></tr>`;
                                    }
                                } else {
                                    Swal.fire("Error!", data.error || "Failed to release pet.", "error");
                                }
                            })
                            .catch(error => {
                                console.error("Error releasing pet:", error);
                                Swal.fire("Error!", "Something went wrong. Please try again.", "error");
                            });
                    }
                });
            }

            function addConfinementReport(petId) {
                Swal.fire({
                    title: "Confinement Daily Report",
                    width: "60%",
                    html: `
                        <form id="confinementForm">
                            <input type="hidden" id="pet_id" name="pet_id" value="${petId}">

                            <div class="form-container">
                                <div class="swal2-row">
                                    <label for="diagnosis">Diagnosis:<span class="required">*</span></label>
                                    <input type="text" id="diagnosis" name="diagnosis" class="swal2-input" required>
                                </div>

                                <div class="swal2-row">
                                    <label for="confinement_date">Confinement Date:<span class="required">*</span></label>
                                    <input type="date" id="confinement_date" name="confinement_date" class="swal2-input" required>
                                </div>

                                <div class="swal2-row">
                                    <label for="iv_fluid_rate">IV Fluid Drip Rate:<span class="required">*</span></label>
                                    <input type="text" id="iv_fluid_rate" name="iv_fluid_rate" class="swal2-input">
                                </div>

                                <div class="swal2-row">
                                    <label for="diet">Diet:<span class="required">*</span></label>
                                    <input type="text" id="diet" name="diet" class="swal2-input">
                                </div>
                            </div>

                            <br><hr><br>
                            <h3>Medications & Extras</h3>
                            <div class="table-container">
                                <table id="medicationTable" class="swal2-table">
                                    <thead>
                                        <tr>
                                            <th>QTY</th>
                                            <th>Medicine</th>
                                            <th>Dose</th>
                                            <th>Route</th>
                                            <th>Observation</th>
                                            <th>AM</th>
                                            <th>PM</th>
                                            <th>Remarks</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="medicationRows"></tbody>
                                </table>
                            </div>

                            <button type="button" class="add-row-btn" onclick="addMedicationRow();">+ Add Row</button>
                        </form>
                    `,
                    showCancelButton: true,
                    confirmButtonText: "Save",
                    focusConfirm: false,
                    preConfirm: () => {
                        saveConfinementReport();
                    }
                });
            }

            function addMedicationRow() {
                let table = document.getElementById("medicationRows");
                let row = document.createElement("tr");

                row.innerHTML = `
                    <td><input type="number" name="qty[]" class="medication-input" required></td>
                    <td><input type="text" name="medicine[]" class="medication-input" required></td>
                    <td><input type="text" name="dose[]" class="medication-input" required></td>
                    <td>
                        <select name="route[]" class="medication-select" required>
                            <option value="IV">IV</option>
                            <option value="Oral">Oral</option>
                            <option value="SC">SC</option>
                        </select>
                    </td>
                    <td><input type="text" name="observation[]" class="medication-input"></td>
                    <td><input type="text" name="am_time[]" class="medication-time" placeholder="HH:MM"></td>
                    <td><input type="text" name="pm_time[]" class="medication-time" placeholder="HH:MM"></td>
                    <td><input type="text" name="remarks[]" class="medication-input"></td>
                    <td><button type="button" class="delete-row-btn" onclick="removeRow(this);">X</button></td>
                `;
                table.appendChild(row);
            }

            function removeRow(button) {
                let row = button.parentNode.parentNode;
                row.parentNode.removeChild(row);
            }

            // Function to submit form via AJAX
            function saveConfinementReport() {
                let formData = new FormData(document.getElementById("confinementForm"));

                fetch("save_confinement_report.php", {
                    method: "POST",
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire("Success!", "Confinement Report Saved Successfully!", "success").then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire("Error!", data.message, "error");
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        Swal.fire("Error!", "An unexpected error occurred.", "error");
                    });
            }

            function applyFilters() {
                const search = document.getElementById("searchInput").value;
                const startDate = document.getElementById("startDate").value;
                const endDate = document.getElementById("endDate").value;
                const order = document.getElementById("sortOrder").value;

                const params = new URLSearchParams({ search, startDate, endDate, order });

                fetch("../src/fetch_filtered_pets.php?" + params.toString())
                    .then(response => response.json()) // ✅ Convert response to JSON
                    .then(data => {
                        const tableBody = document.getElementById("confinedPetsList");

                        // ✅ Clear previous records in tbody
                        tableBody.innerHTML = "";

                        let currentDate = null;
                        let rowsHTML = "";

                        data.forEach(pet => {
                            let confinementDate = pet.ConfinementDate ? new Date(pet.ConfinementDate).toDateString() : "N/A";

                            // ✅ Update only existing date headers (do not create new `<thead>`)
                            if (currentDate !== confinementDate) {
                                rowsHTML += `<tr><th colspan='7' class='date-header'>${confinementDate}</th></tr>`;
                                currentDate = confinementDate;
                            }

                            // ✅ Create a row for each pet
                            rowsHTML += `
                    <tr>
                        <td>${pet.PetCode}</td>
                        <td><a href="pet_profile.php?pet_id=${pet.PetId}">${pet.PetName}</a></td>
                        <td>${pet.PetType}</td>
                        <td>${pet.Weight} kg</td>
                        <td>${pet.ConfinementDate || 'N/A'}</td>
                        <td>N/A</td>
                        <td>
                            <a href="#" class="action-btn add-report-btn" onclick="addConfinementReport('${pet.PetId}'); return false;">Add Report</a>
                            <a href="#" class="action-btn release-btn" onclick="releasePet('${pet.PetId}'); return false;">Release</a>
                        </td>
                    </tr>
                `;
                        });

                        // ✅ Update only the existing <tbody> (confinedPetsList)
                        tableBody.innerHTML = rowsHTML;
                    })
                    .catch(error => console.error("Error fetching filtered data:", error));
            }
            function clearFilters() {
                // ✅ Reset all filter input fields
                document.getElementById("searchInput").value = "";
                document.getElementById("startDate").value = "";
                document.getElementById("endDate").value = "";
                document.getElementById("sortOrder").value = "desc"; // Default sorting order

                // ✅ Re-fetch and display unfiltered data
                applyFilters();
            }
        </script>
</body>

</html>