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

// Pagination setup
$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

$where_clause = [];
$params = [];

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(UserName LIKE ? OR Role LIKE ? OR PageAccessed LIKE ? OR ActionDetails LIKE ?)";
    $params = array_fill(0, 4, $search);
}

// Date filter
if (!empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];
    $where_clause[] = "CreatedAt BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
SELECT UserName, Role, PageAccessed, ActionDetails, CreatedAt
FROM ActivityLog
$where_sql
ORDER BY CreatedAt DESC
LIMIT ?, ?";
$params[] = $offset;
$params[] = $recordsPerPage;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$allActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "SELECT COUNT(*) FROM ActivityLog $where_sql";
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
    <link rel="stylesheet" href="../assets/css/all_activities.css">
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
                <li class="active"><a href="main_dashboard.php">
                        <img src="../assets/images/Icons/Chart 3.png" alt="Chart Icon">Overview</a></li>
                <li><a href="record.php">
                        <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li><a href="staff_view.php">
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
            <h1>All Activities</h1>
        </div>
        <div class="actions">
            <div class="left">
                <input type="text" id="searchInput" placeholder="Search activities..." oninput="fetchActivities()">
                <div class="dropdown">
                    <button class="filter-btn">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <div class="dropdown-content">
                        <strong>Date Filter</strong>
                        <label><input type="radio" name="dateFilter" value="today" onclick="applyFilters()">
                            Today</label>
                        <label><input type="radio" name="dateFilter" value="this_week" onclick="applyFilters()"> This
                            Week</label>
                        <label><input type="radio" name="dateFilter" value="this_month" onclick="applyFilters()"> This
                            Month</label>
                        <label><input type="radio" name="dateFilter" value="custom" onclick="toggleCustomDate(true)">
                            Custom Range</label>

                        <div id="customDateFilters" style="display: none;">
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
        <button onclick="window.location.href='export_activities.php'">Export to CSV</button>
        <table class="staff-table">
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>Role</th>
                    <th>Module</th>
                    <th>Activity</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($allActivities):
                    $currentDate = null; // Track the last printed date
                
                    foreach ($allActivities as $activity):
                        // Extract only the date (ignoring time)
                        $activityDate = date("Y-m-d", strtotime($activity['CreatedAt']));

                        // If this is a new date, print the date header row
                        if ($currentDate !== $activityDate):
                            $currentDate = $activityDate; ?>
                            <tr class="date-header">
                                <td colspan="5"><strong><?= htmlspecialchars(date("F j, Y", strtotime($currentDate))) ?></strong>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- Display the activity -->
                        <tr>
                            <td><?= htmlspecialchars($activity['UserName']); ?></td>
                            <td><?= htmlspecialchars($activity['Role']); ?></td>
                            <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $activity['PageAccessed']))); ?></td>
                            <td><?= html_entity_decode($activity['ActionDetails']); ?></td>
                            <td><?= htmlspecialchars(date("h:i A", strtotime($activity['CreatedAt']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No activities found.</td>
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
    </div>
    <script>
        setInterval(fetchActivities, 30000); // Refresh every 30 seconds
    </script>
    <script>
        function fetchActivities() {
            const search = document.getElementById('searchInput').value;

            fetch(`fetch_activities.php?search=${encodeURIComponent(search)}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById("activityList").innerHTML = data;
                })
                .catch(error => console.error("Error fetching activities:", error));
        }
    </script>
</body>

</html>