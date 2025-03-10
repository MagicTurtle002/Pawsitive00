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

// ✅ Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(i.InvoiceNumber LIKE ? 
                        OR p.Name LIKE ? 
                        OR CONCAT(o.FirstName, ' ', o.LastName) LIKE ?)";
    $params = array_fill(0, 3, $search);
}

// ✅ Date range filter
if (!empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];
    $where_clause[] = "i.InvoiceDate BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

// ✅ Status filter
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    if ($status == 'pending') {
        $where_clause[] = "i.Status = 'Pending'";
    } elseif ($status == 'overdue') {
        $where_clause[] = "i.Status = 'Overdue'";
    } elseif ($status == 'partial') {
        $where_clause[] = "i.Status = 'Partial Payment'";
    } elseif ($status == 'paid') {
        $where_clause[] = "i.Status = 'Paid'";
    }
}

// ✅ Construct WHERE clause
$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

// ✅ Fetch invoices
$query = "
    SELECT 
        i.InvoiceId,
        i.InvoiceNumber,
        i.InvoiceDate,
        i.TotalAmount,
        i.Status,
        p.Name AS PetName,
        CONCAT(o.FirstName, ' ', o.LastName) AS OwnerName
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    $where_sql
    ORDER BY i.InvoiceDate DESC
    LIMIT ?, ?";

$params[] = $offset;
$params[] = $recordsPerPage;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Count total invoices
$countQuery = "
SELECT COUNT(*) 
FROM Invoices i
INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
INNER JOIN Pets p ON a.PetId = p.PetId
INNER JOIN Owners o ON p.OwnerId = o.OwnerId
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
    <title>Invoice List - Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="../assets/css/invoice_list.css">
    <script src="../assets/js/invoice_list.js"></script>
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
            <li><a href="main_dashboard.php">Overview</a></li>
            <li><a href="record.php">Records</a></li>
            <li><a href="staff.php">Staff</a></li>
            <li><a href="appointment.php">Appointments</a></li>
            <li class="active"><a href="invoice_list.php">Invoices</a></li>
        </ul>
    </nav>
</div>

<div class="main-content">
    <div class="header">
        <h1>Invoices</h1>
        <div class="actions">
            <div class="left">
                <input type="text" id="searchInput" placeholder="Search invoices..." oninput="applyFilters()">
                <div class="dropdown">
                    <button class="filter-btn">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <div class="dropdown-content">
                        <strong>Status Filter</strong>
                        <label><input type="radio" name="status" value="all" onclick="applyFilters()"> All</label>
                        <label><input type="radio" name="status" value="pending" onclick="applyFilters()"> Pending</label>
                        <label><input type="radio" name="status" value="overdue" onclick="applyFilters()"> Overdue</label>
                        <label><input type="radio" name="status" value="partial" onclick="applyFilters()"> Partial Payment</label>
                        <label><input type="radio" name="status" value="paid" onclick="applyFilters()"> Paid</label>

                        <hr>

                        <strong>Date Filter</strong>
                        <label><input type="radio" name="dateFilter" value="today" onclick="applyFilters()"> Today</label>
                        <label><input type="radio" name="dateFilter" value="this_week" onclick="applyFilters()"> This Week</label>
                        <label><input type="radio" name="dateFilter" value="this_month" onclick="applyFilters()"> This Month</label>
                        <label><input type="radio" name="dateFilter" value="custom" onclick="toggleCustomDate(true)"> Custom Range</label>

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
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Invoice ID</th>
                <th>Invoice Number</th>
                <th>Date Issued</th>
                <th>Pet Name</th>
                <th>Owner Name</th>
                <th>Total Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="invoiceList">
            <?php if (count($invoices) > 0): ?>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?= htmlspecialchars($invoice['InvoiceId']) ?></td>
                        <td><?= htmlspecialchars($invoice['InvoiceNumber']) ?></td>
                        <td><?= htmlspecialchars($invoice['InvoiceDate']) ?></td>
                        <td><?= htmlspecialchars($invoice['PetName']) ?></td>
                        <td><?= htmlspecialchars($invoice['OwnerName']) ?></td>
                        <td>₱<?= number_format($invoice['TotalAmount'], 2) ?></td>
                        <td><?= htmlspecialchars($invoice['Status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">No invoices found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <a href="?page=<?= max(0, $currentPage - 1) ?>">&laquo; Previous</a>
        <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>">Next &raquo;</a>
    </div>
</div>

<script src="../assets/js/invoice_list.js"></script>
</body>
</html>