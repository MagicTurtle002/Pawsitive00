<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Staff';
$role = $_SESSION['Role'] ?? 'Role';

$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;
$where_clause = [];
$params = [];

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(Appointments.AppointmentId LIKE ? 
                        OR Pets.Name LIKE ? 
                        OR Appointments.AppointmentCode LIKE ? 
                       OR COALESCE(Services.ServiceName, '') LIKE ? 
                        OR CONCAT(Owners.FirstName, ' ', Owners.LastName) LIKE ?)";
    $params = array_fill(0, 5, $search);
}

// Date filter
if (!empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];
    $where_clause[] = "appointments.AppointmentDate BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

// Status filter
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    if ($status == 'today') {
        $where_clause[] = "Appointments.AppointmentDate = CURDATE()";
    } elseif ($status == 'upcoming') {
        $where_clause[] = "Appointments.AppointmentDate > CURDATE() AND Appointments.Status = 'Pending'";
    } elseif ($status == 'done') {
        $where_clause[] = "Appointments.Status = 'Done'";
    } elseif ($status == 'declined') {
        $where_clause[] = "Appointments.Status = 'Declined'";
    }
}

$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
    SELECT 
        Appointments.AppointmentId,
        Owners.OwnerId,
        CONCAT(Owners.FirstName, ' ', Owners.LastName) AS OwnerName,
        Owners.Email,
        Owners.Phone,
        Pets.PetCode,
        Pets.PetId,
        Pets.Name AS PetName,
        Species.SpeciesName AS PetType,
        Services.ServiceName AS Service,
        Appointments.AppointmentDate,
        Appointments.AppointmentTime,
        Appointments.Status,
        Appointments.Reason
    FROM Appointments
    INNER JOIN Pets ON Appointments.PetId = Pets.PetId
    INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Species ON Pets.SpeciesId = Species.Id
    LEFT JOIN Services ON Appointments.ServiceId = Services.ServiceId
    $where_sql
    ORDER BY Appointments.AppointmentDate DESC, Appointments.AppointmentTime DESC
    LIMIT ?, ?";
$params[] = $offset;
$params[] = $recordsPerPage;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "
SELECT COUNT(*) 
FROM Appointments
INNER JOIN Pets ON Appointments.PetId = Pets.PetId
INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
LEFT JOIN Species ON Pets.SpeciesId = Species.Id
LEFT JOIN Services ON Appointments.ServiceId = Services.ServiceId
$where_sql";
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
    <link rel="stylesheet" href="../assets/css/appointment_list.css">
    <script src="../assets/js/record.js"></script>
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
                <li><a href="record.php">
                        <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li><a href="staff.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li class="active"><a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 3.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice</a></>
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
            <h1>Appointments</h1>
            <div class="actions">
                <div class="left">
                    <input type="text" id="searchInput" placeholder="Search appointments..." />
                    <div class="dropdown">
                        <button class="filter-btn">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <div class="dropdown-content">
                            <strong>Status Filter</strong>
                            <label>
                                <input type="radio" name="status" value="all" onclick="applyFilters()"> All Appointments
                            </label>
                            <label>
                                <input type="radio" name="status" value="pending" onclick="applyFilters()"> Pending
                            </label>
                            <label>
                                <input type="radio" name="status" value="confirmed" onclick="applyFilters()"> Confirmed
                            </label>
                            <label>
                                <input type="radio" name="status" value="done" onclick="applyFilters()"> Done
                            </label>
                            <label>
                                <input type="radio" name="status" value="paid" onclick="applyFilters()"> Paid
                            </label>
                            <label>
                                <input type="radio" name="status" value="declined" onclick="applyFilters()"> Declined
                            </label>

                            <hr>

                            <strong>Date Filter</strong>
                            <label>
                                <input type="radio" name="dateFilter" value="today" onclick="applyFilters()"> Today
                            </label>
                            <label>
                                <input type="radio" name="dateFilter" value="this_week" onclick="applyFilters()"> This
                                Week
                            </label>
                            <label>
                                <input type="radio" name="dateFilter" value="this_month" onclick="applyFilters()"> This
                                Month
                            </label>
                            <label>
                                <input type="radio" name="dateFilter" value="custom" onclick="toggleCustomDate(true)">
                                Custom Range
                            </label>

                            <div id="customDateFilters" style="display: none; padding-top: 5px;">
                                <label>Start Date: <input type="date" id="startDate" onchange="applyFilters()"></label>
                                <label>End Date: <input type="date" id="endDate" onchange="applyFilters()"></label>
                            </div>

                            <hr>

                            <button type="submit" class="apply-btn">Apply Filter</button>
                            <button type="button" class="clear-btn" onclick="resetFilters()">Clear Filter</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <table class="staff-table">
            <thead>
                <tr>
                    <th><a href="#" class="sortable" data-column="AppointmentCode">Appointment Id</a></th>
                    <th><a href="#" class="sortable" data-column="PetName">Pet Name</a></th>
                    <th><a href="#" class="sortable" data-column="Status">Status</a></th>
                    <th><a href="#" class="sortable" data-column="Service">Service</a></th>
                    <th><a href="#" class="sortable" data-column="Action">Actions</a></th>
                </tr>
            </thead>
            <tbody id="appointmentList">
                <?php
                if (count($appointments) > 0):
                    $currentDate = null;
                    foreach ($appointments as $appointment):
                        $appointmentDate = htmlspecialchars($appointment['AppointmentDate']);
                        $status = htmlspecialchars($appointment['Status']);
                        $petId = htmlspecialchars($appointment['PetId']);

                        // Display a new header when the date changes
                        if ($currentDate !== $appointmentDate):
                            $currentDate = $appointmentDate;
                            ?>
                            <tr class="date-header">
                                <td colspan="5"><strong><?= date("F j, Y", strtotime($currentDate)) ?></strong></td>
                            </tr>
                            <?php
                        endif;

                        // Make the row clickable if the status is "Done" or "Paid"
                        $clickable = ($status === 'Done' || $status === 'Paid');
                        ?>
                        <tr <?= $clickable ? 'onclick="goToPetProfile(' . $petId . ')" style="cursor:pointer; background-color: #f8f9fa;"' : '' ?>>
                            <td><?= htmlspecialchars($appointment['AppointmentId']) ?></td>
                            <td><?= htmlspecialchars($appointment['PetName']) ?></td>
                            <td><?= $status ?></td>
                            <td><?= htmlspecialchars($appointment['Service']) ?></td>
                            <td>
                                <?php if ($status === 'Done'): ?>
                                    <a href="generate_invoice.php?id=<?= $appointment['AppointmentId'] ?>"
                                        class="btn btn-success btn-sm">Generate Invoice</a>

                                <?php elseif ($status === 'Confirmed'): ?>
                                    <a href="mark_done.php?id=<?= $appointment['AppointmentId'] ?>"
                                        class="btn btn-primary btn-sm">Mark as Done</a>
                                    <a href="start_consultation.php?id=<?= $appointment['AppointmentId'] ?>"
                                        class="btn btn-info btn-sm">Start Consultation</a>
                                    <a href="cancel_appointment.php?id=<?= $appointment['AppointmentId'] ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>

                                <?php elseif ($status === 'Pending'): ?>
                                    <a href="confirm_appointment.php?id=<?= $appointment['AppointmentId'] ?>"
                                        class="btn btn-success btn-sm">Confirm</a>
                                    <a href="decline_appointment.php?id=<?= $appointment['AppointmentId'] ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to decline this appointment?')">Decline</a>
                                    <a href="reschedule_appointment.php?id=<?= $appointment['AppointmentId'] ?>"
                                        class="btn btn-warning btn-sm">Reschedule</a>

                                <?php elseif ($status === 'Declined'): ?>
                                    <span class="text-danger">Reason for cancellation:
                                        <?= htmlspecialchars($appointment['Reason'] ?? 'No reason provided') ?></span>

                                <?php else: ?>
                                    <span class="text-muted">No actions available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    endforeach;
                else:
                    ?>
                    <tr>
                        <td colspan="5">No appointments found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php if ($totalPages > 1): ?>
                    <?php if ($currentPage > 0): ?>
                        <li><a href="?page=0" aria-label="First">First</a></li>
                    <?php endif; ?>

                    <?php if ($currentPage > 0): ?>
                        <li><a href="?page=<?= max(0, $currentPage - 1) ?>" aria-label="Previous">&laquo; Previous</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = 0; $i < $totalPages; $i++): ?>
                    <?php if ($i == 0 || $i == $totalPages - 1 || abs($i - $currentPage) <= 2): ?>
                        <li>
                            <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active" aria-current="page"' : '' ?>>
                                <?= $i + 1 ?>
                            </a>
                        </li>
                    <?php elseif ($i == 1 || $i == $totalPages - 2): ?>
                        <li><span>...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($totalPages > 1): ?>
                    <?php if ($currentPage < $totalPages - 1): ?>
                        <li><a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>" aria-label="Next">Next &raquo;</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($currentPage < $totalPages - 1): ?>
                        <li><a href="?page=<?= $totalPages - 1 ?>" aria-label="Last">Last</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <p class="pagination-info">Page <?= $currentPage + 1 ?> of <?= $totalPages ?></p>
        </nav>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const searchInput = document.getElementById("searchInput");
                const appointmentList = document.getElementById("appointmentList");

                if (!appointmentList) {
                    console.error("Error: 'appointmentList' element not found in the DOM.");
                    return;
                }

                function debounce(func, delay) {
                    let timeout;
                    return function (...args) {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => func.apply(this, args), delay);
                    };
                }

                function fetchAppointments() {
                    const status = document.querySelector('input[name="status"]:checked')?.value || "";
                    const dateFilter = document.querySelector('input[name="dateFilter"]:checked')?.value || "";
                    const startDate = document.getElementById("startDate").value;
                    const endDate = document.getElementById("endDate").value;

                    if (dateFilter !== "custom") {
                        toggleCustomDate(false);
                    }

                    const queryParams = new URLSearchParams({
                        search: searchInput.value.trim(),
                        status,
                        dateFilter,
                        startDate,
                        endDate
                    });

                    fetch("../src/fetch_appointments.php?" + queryParams.toString())
                        .then(response => response.json())  // Parse as JSON instead of text
                        .then(data => {
                            let tableBody = document.getElementById("appointmentList");
                            tableBody.innerHTML = ""; // Clear existing table content

                            if (data.length === 0) {
                                tableBody.innerHTML = `<tr><td colspan="5">No appointments found.</td></tr>`;
                                return;
                            }

                            let currentDate = null;

                            data.forEach(appointment => {
                                let appointmentDate = appointment.AppointmentDate;
                                let formattedDate = new Date(appointmentDate).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

                                // If the date changes, insert a new date header row
                                if (currentDate !== appointmentDate) {
                                    currentDate = appointmentDate;
                                    tableBody.innerHTML += `
                    <tr class="date-header">
                        <td colspan="5"><strong>${formattedDate}</strong></td>
                    </tr>`;
                                }

                                let actionButtons = "";

                                switch (appointment.Status) {
                                    case "Done":
                                        actionButtons = `<a href="generate_invoice.php?id=${appointment.AppointmentId}" class="btn btn-success btn-sm">Generate Invoice</a>`;
                                        break;
                                    case "Confirmed":
                                        actionButtons = `<a href="mark_done.php?id=${appointment.AppointmentId}" class="btn btn-primary btn-sm">Mark as Done</a>
                                     <a href="start_consultation.php?id=${appointment.AppointmentId}" class="btn btn-info btn-sm">Start Consultation</a>
                                     <a href="cancel_appointment.php?id=${appointment.AppointmentId}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>`;
                                        break;
                                    case "Pending":
                                        actionButtons = `<a href="confirm_appointment.php?id=${appointment.AppointmentId}" class="btn btn-success btn-sm">Confirm</a>
                                     <a href="decline_appointment.php?id=${appointment.AppointmentId}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to decline this appointment?')">Decline</a>
                                     <a href="reschedule_appointment.php?id=${appointment.AppointmentId}" class="btn btn-warning btn-sm">Reschedule</a>`;
                                        break;
                                    case "Declined":
                                        actionButtons = `<span class="text-danger">Reason for cancellation: ${appointment.Reason || 'No reason provided'}</span>`;
                                        break;
                                    default:
                                        actionButtons = `<span class="text-muted">No actions available</span>`;
                                }

                                let row = `<tr ${appointment.Status === 'Done' || appointment.Status === 'Paid' ? `onclick="goToPetProfile(${appointment.PetId})" style="cursor:pointer; background-color: #f8f9fa;"` : ''}>
                <td>${appointment.AppointmentId}</td>
                <td>${appointment.PetName}</td>
                <td>${appointment.Status}</td>
                <td>${appointment.Service || 'N/A'}</td>
                <td>${actionButtons}</td>
            </tr>`;

                                tableBody.innerHTML += row;
                            });
                        })
                        .catch(error => console.error("Error fetching appointments:", error));
                }

                function applyFilters() {
                    fetchAppointments();
                }

                function resetFilters() {
                    document.querySelectorAll('input[name="status"]').forEach(el => el.checked = false);
                    document.querySelectorAll('input[name="dateFilter"]').forEach(el => el.checked = false);
                    document.getElementById("startDate").value = "";
                    document.getElementById("endDate").value = "";
                    searchInput.value = ""; // Reset search field

                    fetchAppointments(true); // Fetch all data again
                }

                function toggleCustomDate(show) {
                    document.getElementById("customDateFilters").style.display = show ? "block" : "none";
                }

                searchInput.addEventListener("input", debounce(fetchAppointments, 300));
                document.querySelectorAll('input[name="status"]').forEach(el => el.addEventListener("change", fetchAppointments));
                document.querySelectorAll('input[name="dateFilter"]').forEach(el => el.addEventListener("change", fetchAppointments));
                document.getElementById("startDate").addEventListener("change", fetchAppointments);
                document.getElementById("endDate").addEventListener("change", fetchAppointments);

                document.querySelector(".apply-btn").addEventListener("click", fetchAppointments);
                document.querySelector(".clear-btn").addEventListener("click", resetFilters);
            });

            function fetchAppointments(isReset = false) {
                const status = isReset ? "" : document.querySelector('input[name="status"]:checked')?.value || "";
                const dateFilter = isReset ? "" : document.querySelector('input[name="dateFilter"]:checked')?.value || "";
                const startDate = isReset ? "" : document.getElementById("startDate").value;
                const endDate = isReset ? "" : document.getElementById("endDate").value;
                const searchQuery = isReset ? "" : searchInput.value.trim();

                if (!isReset && dateFilter !== "custom") {
                    toggleCustomDate(false);
                }

                const queryParams = new URLSearchParams({
                    search: searchQuery,
                    status,
                    dateFilter,
                    startDate,
                    endDate,
                    page: 0 // Reset to first page when searching or filtering
                });

                fetch("../src/fetch_appointments.php?" + queryParams.toString())
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById("appointmentList").innerHTML = html;
                    })
                    .catch(error => console.error("Error fetching appointments:", error));
            }
        </script>
        <script></script>

</body>

</html>