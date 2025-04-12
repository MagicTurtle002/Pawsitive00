<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
ob_start();

require_once __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/log_helpers.php';

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

$where_clause = [];
$where_clause[] = "Pets.IsArchived = 1";
$params = [];

if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(LOWER(Pets.PetCode) LIKE LOWER(?) 
                        OR LOWER(Pets.Name) LIKE LOWER(?) 
                        OR LOWER(CONCAT(Owners.FirstName, ' ', Owners.LastName)) LIKE LOWER(?) 
                        OR LOWER(Species.SpeciesName) LIKE LOWER(?))";
    array_push($params, $search, $search, $search, $search);
}

// ✅ Ensure WHERE SQL is Properly Built
$where_sql = count($where_clause) > 0 ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
    SELECT DISTINCT
        Owners.OwnerId,
        CONCAT(Owners.FirstName, ' ', Owners.LastName) AS owner_name,
        Owners.Email,
        Owners.Phone,
        Pets.PetId,
        Pets.Name AS PetName,
        Pets.PetCode,
        Species.SpeciesName AS PetType
    FROM Owners
    LEFT JOIN Pets ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Species ON Pets.SpeciesId = Species.Id
    $where_sql
    ORDER BY Pets.PetCode ASC
    LIMIT ?, ?
";

$stmt = $pdo->prepare($query);
$params[] = $offset;
$params[] = $recordsPerPage;
$stmt->execute($params);
$archivedPets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "
    SELECT COUNT(*) 
    FROM Pets
    LEFT JOIN Owners ON Pets.OwnerId = Owners.OwnerId
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
            <button onclick="window.location.href='../logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Archive Pets</h1>
            <div class="actions">
                <form method="GET" action="archive_list.php" class="filter-container">

                    <input type="text" id="searchInput" name="search" placeholder="Search archive pets..."
                        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        oninput="applyFilters()">

                    <div class="dropdown-filter">
                        <button type="button" class="filter-btn">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <div class="dropdown-content">
                            <!-- Date Filter -->
                            <label>
                                <input type="radio" name="filter" value="all" <?= isset($_GET['filter']) && $_GET['filter'] === 'all' ? 'checked' : ''; ?>> All
                            </label>
                            <label>
                                <input type="radio" name="filter" value="today" <?= isset($_GET['filter']) && $_GET['filter'] === 'today' ? 'checked' : ''; ?>> Today
                            </label>
                            <label>
                                <input type="radio" name="filter" value="lastWeek" <?= isset($_GET['filter']) && $_GET['filter'] === 'lastWeek' ? 'checked' : ''; ?>> Last Week
                            </label>
                            <label>
                                <input type="radio" name="filter" value="lastMonth" <?= isset($_GET['filter']) && $_GET['filter'] === 'lastMonth' ? 'checked' : ''; ?>> Last Month
                            </label>
                            <hr>
                            <!-- Ascending / Descending -->
                            <label>
                                <input type="radio" name="order" value="asc" <?= isset($_GET['order']) && $_GET['order'] === 'asc' ? 'checked' : ''; ?>> Ascending
                            </label>
                            <label>
                                <input type="radio" name="order" value="desc" <?= isset($_GET['order']) && $_GET['order'] === 'desc' ? 'checked' : ''; ?>> Descending
                            </label>
                            <hr>
                            <button type="submit" class="apply-btn">Apply Filter</button>
                            <button type="button" class="clear-btn" onclick="location.href='record.php'">Clear
                                Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Pet ID</th>
                    <th>Pet Name</th>
                    <th>Pet Type</th>
                    <th>Owner Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="archivedList">
                <?php if (count($archivedPets) > 0): ?>
                    <?php foreach ($archivedPets as $pet): ?>
                        <tr id="pet-row-<?= $pet['PetId'] ?>">
                            <td><?= htmlspecialchars($pet['PetCode'] ?? 'No information found') ?></td>
                            <td><?= htmlspecialchars($pet['PetName'] ?? 'No information found') ?></td>
                            <td><?= htmlspecialchars($pet['PetType'] ?? 'No information found') ?></td>
                            <td><?= htmlspecialchars($pet['owner_name'] ?? 'No information found') ?></td>
                            <td><?= htmlspecialchars($pet['Email'] ?? 'No information found') ?></td>
                            <td><?= htmlspecialchars($pet['Phone'] ?? 'No information found') ?></td>
                            <td>
                                <button class="restore-btn" onclick="restorePet(<?= $pet['PetId'] ?>)">
                                    Restore
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No archived pets found.</td>
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
            function applyFilters() {
                const search = document.getElementById("searchInput").value;

                fetch(`../src/archived_search.php?search=${encodeURIComponent(search)}`)
                    .then(response => response.json())
                    .then(data => {
                        const tableBody = document.getElementById("archivedList");
                        tableBody.innerHTML = "";

                        let rowsHTML = "";

                        if (data.length === 0) {
                            rowsHTML = `<tr><td colspan="7">No archived pets found.</td></tr>`;
                        } else {
                            data.forEach(pet => {
                                rowsHTML += `
                            <tr id="pet-row-${pet.PetId}">
                                <td>${pet.PetCode || 'No information found'}</td>
                                <td>${pet.PetName || 'No information found'}</td>
                                <td>${pet.PetType || 'No information found'}</td>
                                <td>${pet.owner_name || 'No information found'}</td>
                                <td>${pet.Email || 'No information found'}</td>
                                <td>${pet.Phone || 'No information found'}</td>
                                <td>
                                    <button class="restore-btn" onclick="restorePet(${pet.PetId})">
                                        Restore
                                    </button>
                                </td>
                            </tr>
                        `;
                            });
                        }

                        tableBody.innerHTML = rowsHTML;
                    })
                    .catch(error => console.error("Error fetching filtered data:", error));
            }

            function restorePet(petId) {
                Swal.fire({
                    title: "Are you sure?",
                    text: "This pet will be restored to active records.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, restore it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch("../src/restore_pet.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: `petId=${petId}`
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire("Restored!", "Pet has been restored successfully.", "success");

                                    // Remove the restored pet's row from the table
                                    const row = document.getElementById(`pet-row-${petId}`);
                                    if (row) row.remove();

                                    // Check if the table is empty and show a message if needed
                                    const tableBody = document.querySelector("tbody");
                                    if (tableBody.children.length === 0) {
                                        tableBody.innerHTML = `
                                        <tr>
                                            <td colspan="7">No archived pets found.</td>
                                        </tr>
                                    `;
                                    }
                                } else {
                                    Swal.fire("Error!", data.error || "Failed to restore pet.", "error");
                                }
                            })
                            .catch(error => {
                                console.error("Error restoring pet:", error);
                                Swal.fire("Error!", "Something went wrong. Please try again.", "error");
                            });
                    }
                });
            }
        </script>
</body>

</html>