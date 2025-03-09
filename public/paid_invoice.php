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

$where_clause = ["Invoices.Status = 'Paid'"];
$params = [];

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(Invoices.InvoiceId LIKE ? OR Owners.FirstName LIKE ? OR Owners.LastName LIKE ? OR Invoices.TotalAmount LIKE ? OR Invoices.InvoiceDate LIKE ? )";
    $params = array_fill(0, 5, $search);
}

// Date filter
if (!empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];
    $where_clause[] = "Invoices.InvoiceDate BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
    SELECT 
        Invoices.InvoiceId,
        CONCAT(Owners.FirstName, ' ', Owners.LastName) AS OwnerName,
        Owners.Email,
        Owners.Phone,
        Invoices.InvoiceDate,
        Invoices.TotalAmount,
        Invoices.Status
    FROM Invoices
    INNER JOIN Owners ON Invoices.OwnerId = Owners.OwnerId
    $where_sql
    ORDER BY Invoices.InvoiceDate DESC
    LIMIT ?, ?";
$params[] = $offset;
$params[] = $recordsPerPage;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "SELECT COUNT(*) FROM Invoices INNER JOIN Owners ON Invoices.OwnerId = Owners.OwnerId $where_sql";
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
    <title>Paid Invoices</title>
    <link rel="stylesheet" href="../assets/css/invoice_list.css">
</head>
<body>
    <div class="main-content">
        <h1>Paid Invoices</h1>
        <input type="text" id="searchInput" placeholder="Search invoices..." />
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Owner Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Invoice Date</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($invoices) > 0): ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?= htmlspecialchars($invoice['InvoiceId']) ?></td>
                            <td><?= htmlspecialchars($invoice['OwnerName']) ?></td>
                            <td><?= htmlspecialchars($invoice['Email']) ?></td>
                            <td><?= htmlspecialchars($invoice['Phone']) ?></td>
                            <td><?= htmlspecialchars($invoice['InvoiceDate']) ?></td>
                            <td>$<?= htmlspecialchars(number_format($invoice['TotalAmount'], 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No paid invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination">
            <a href="?page=<?= max(0, $currentPage - 1) ?>">&laquo; Previous</a>
            <?php for ($i = 0; $i < $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i + 1 ?></a>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>">Next &raquo;</a>
        </div>
    </div>
</body>
</html>
