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
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10;
$offset = $currentPage * $recordsPerPage;

// ✅ Build WHERE conditions
$where_clause = [];
$params = [];

$where_clause[] = "i.Status IN ('Pending', 'Overdue', 'Partial Payment')";

if (!empty($searchQuery)) {
    $search = '%' . strtolower($searchQuery) . '%';
    $where_clause[] = "(LOWER(i.InvoiceNumber) LIKE LOWER(?) 
                        OR LOWER(CONCAT(o.FirstName, ' ', o.LastName)) LIKE LOWER(?) 
                        OR LOWER(p.Name) LIKE LOWER(?))";
    array_push($params, $search, $search, $search);
}

// ✅ Construct WHERE clause properly
$whereClause = count($where_clause) > 0 ? ' WHERE ' . implode(' AND ', $where_clause) : '';

// ✅ Fetch total records count
$countQuery = "
    SELECT COUNT(*) AS total
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    $whereClause
";

$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// ✅ Fetch Unpaid Invoices with Pagination
$query = "
    SELECT i.InvoiceId, i.InvoiceNumber, i.InvoiceDate AS DateIssued, i.TotalAmount, i.Status,
           a.AppointmentDate AS DueDate, 
           p.Name AS PetName, CONCAT(o.FirstName, ' ', o.LastName) AS OwnerName,
           s.ServiceName
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    $whereClause
    ORDER BY i.InvoiceDate ASC
    LIMIT ?, ?
";

// ✅ Add pagination parameters
array_push($params, $offset, $recordsPerPage);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$unpaidInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="../assets/css/staff_view.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/staff_view.js"></script>
    <style>
        .toast-container {
            position: fixed;
            bottom: 20px;
            /* Position at the bottom */
            right: 20px;
            /* Position on the right side */
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            /* Space between multiple toasts */
        }

        /* Success Toast */
        .success-toast {
            color: #155724;
            /* Green text */
            background-color: #d4edda;
            /* Light green background */
            padding: 12px 20px;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            animation: slide-in 0.5s ease, fade-out 0.5s ease 3s;
            opacity: 1;
        }

        /* Error Toast */
        .error-toast {
            color: #721c24;
            /* Red text */
            background-color: #f8d7da;
            /* Light red background */
            padding: 12px 20px;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            animation: slide-in 0.5s ease, fade-out 0.5s ease 3s;
            opacity: 1;
        }

        @keyframes slide-in {
            from {
                transform: translateX(100%);
                /* Start off-screen */
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fade-out {
            to {
                opacity: 0;
                transform: translateX(100%);
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
            <ul class="nav-links">
                <h3>Hello, <?= htmlspecialchars($userName) ?></h3>
                <h4><?= htmlspecialchars($role) ?></h4>
                <br>
                <li><a href="main_dashboard.php">
                        <img src="../assets/images/Icons/Chart 1.png" alt="Overview Icon">Overview</a></li>
                <li><a href="record.php">
                        <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li><a href="staff.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li><a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 1.png" alt="Schedule Icon">Schedule</a></li>
                <li class="active"><a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 3.png" alt="Schedule Icon">Invoice</a></li>
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
            <h1>Unpaid Invoices</h1>
            <div id="toastContainer" class="toast-container">
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="success-toast" id="successToast">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($_SESSION['success']); ?>
                    </div>
                    <?php unset($_SESSION['success']); // Clear message after displaying ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="error-toast" id="errorToast">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); // Clear message after displaying ?>
                <?php endif; ?>
            </div>

            <div class="actions">
                <div class="left">
                    <input type="text" id="searchInput" name="search" placeholder="Search staff..."
                        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        oninput="applyFilters()">

                    <div class="dropdown">
                        <button class="filter-btn">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <div class="dropdown-content">
                            <label>
                                <input type="radio" name="filter" value="name" <?= $filterQuery == 'name' ? 'checked' : '' ?>> Name
                            </label>
                            <label>
                                <input type="radio" name="filter" value="role" <?= $filterQuery == 'role' ? 'checked' : '' ?>> Role
                            </label>
                            <hr>
                            <label>
                                <input type="radio" name="order" value="ASC" <?= $orderQuery == 'ASC' ? 'checked' : '' ?>>
                                Ascending
                            </label>
                            <label>
                                <input type="radio" name="order" value="DESC" <?= $orderQuery == 'DESC' ? 'checked' : '' ?>> Descending
                            </label>
                            <hr>
                            <button type="submit" class="apply-btn">Apply Filter</button>
                            <button type="button" class="clear-btn"
                                onclick="window.location.href='staff_view.php'">Clear Filter</button>
                        </div>
                    </div>
                </div>
                <button class="add-btn" onclick="location.href='invoice_list.php'">View all</button>
            </div>
        </div>
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Invoice Number</th>
                    <th>Date Issued</th>
                    <th>Due Date</th>
                    <th>Pet Name</th>
                    <th>Owner Name</th>
                    <th>Service Provided</th>
                    <th>Total Amount Due</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="staffList">
                <?php if (!empty($unpaidInvoices)): ?>
                    <?php foreach ($unpaidInvoices as $invoice): ?>
                        <tr class="staff-row">
                            <td><?= htmlspecialchars($invoice['InvoiceId']) ?></td>
                            <td><?= htmlspecialchars($invoice['InvoiceNumber']) ?></td>
                            <td><?= htmlspecialchars($invoice['DateIssued']) ?></td>
                            <td><?= htmlspecialchars($invoice['DueDate']) ?></td>
                            <td><?= htmlspecialchars($invoice['PetName']) ?></td>
                            <td><?= htmlspecialchars($invoice['OwnerName']) ?></td>
                            <td><?= htmlspecialchars($invoice['ServiceName']) ?></td>
                            <td>₱<?= number_format($invoice['TotalAmount'], 2) ?></td>
                            <td><?= htmlspecialchars($invoice['Status']) ?></td>
                            <td>
                                <button class="mark-paid-btn" data-id="<?= $invoice['InvoiceId'] ?>">Mark as Paid</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">No unpaid invoices found.</td>
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
        function applyFilters() {
            const search = document.getElementById('searchInput').value;

            fetch(`../src/invoice_search.php?search=${encodeURIComponent(search)}`)
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById("staffList");
                    tableBody.innerHTML = ""; // Clear existing results

                    let rowsHTML = "";

                    if (data.length === 0) {
                        rowsHTML = `<tr><td colspan="10">No invoice found</td></tr>`;
                    } else {
                        data.forEach(invoice => {
                            rowsHTML += `
                        <tr class="staff-row">
                            <td>${invoice.InvoiceId}</td>
                            <td>${invoice.InvoiceNumber}</td>
                            <td>${invoice.DateIssued}</td>
                            <td>${invoice.DueDate}</td>
                            <td>${invoice.PetName}</td>
                            <td>${invoice.OwnerName}</td>
                            <td>${invoice.ServiceName}</td>
                            <td>₱${parseFloat(invoice.TotalAmount).toFixed(2)}</td>
                            <td>${invoice.Status}</td>
                            <td>
                                <button class="mark-paid-btn" data-id="${invoice.InvoiceId}">Mark as Paid</button>
                            </td>
                        </tr>
                    `;
                        });
                    }

                    tableBody.innerHTML = rowsHTML;
                })
                .catch(error => console.error("Error fetching invoices:", error));
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".mark-paid-btn").forEach(button => {
                button.addEventListener("click", function () {
                    const invoiceId = this.dataset.id;

                    Swal.fire({
                        title: 'Confirm Payment',
                        text: "Are you sure you want to mark this invoice as paid?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, mark as Paid!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('../src/mark_invoice_paid.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ invoice_id: invoiceId })
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire({
                                            title: 'Success!',
                                            text: 'Invoice marked as Paid.',
                                            icon: 'success',
                                            timer: 2000,
                                            showConfirmButton: false
                                        }).then(() => location.reload());
                                    } else {
                                        Swal.fire('Error!', data.message, 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Fetch error:', error);
                                    Swal.fire('Error!', 'Something went wrong.', 'error');
                                });
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>