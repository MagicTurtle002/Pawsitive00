<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../config/dbh.inc.php';
require '../../src/helpers/session_helpers.php';
require '../../src/helpers/auth_helpers.php';

session_start();
//checkAuthentication($pdo);

$ownerId = $_SESSION['OwnerId'];
$ownerEmail = $_SESSION['Email'];
$ownerName = $_SESSION['OwnerName'];

$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10; // Adjust the number of records per page
$offset = $currentPage * $recordsPerPage;

$stmt = $pdo->prepare("
    SELECT i.InvoiceId, i.InvoiceNumber, i.InvoiceDate, i.TotalAmount, i.Status, i.PaidAt,
           a.AppointmentDate, p.Name AS PetName, s.ServiceName
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE o.OwnerId = ?
    ORDER BY i.InvoiceDate DESC
    LIMIT ?, ?
");
$stmt->execute([$ownerId, $offset, $recordsPerPage]); 
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "SELECT COUNT(*) FROM Invoices i
               INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
               INNER JOIN Pets p ON a.PetId = p.PetId
               INNER JOIN Owners o ON p.OwnerId = o.OwnerId
               WHERE o.OwnerId = ?";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute([$ownerId]);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive | Invoice</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/invoice.css">
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
                <li><a href="pet_record.php">Record</a></li>
                <li><a href="invoice.php" class="active">Invoice</a></li>
            </ul>
            <div class="profile-dropdown">
                <img src="../../assets/images/Icons/User 1.png" alt="Profile Icon" class="profile-icon">
                <div class="dropdown-content">
                    <a href="settings.php"><img src="../../assets/images/Icons/Settings 2.png" alt="Settings">Settings</a>
                    <a href=""><img src="../../assets/images/Icons/Sign out.png" alt="Sign Out">Sign Out</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-text">
                <h1>Invoice History</h1>
            </div>
        </section>
        <div class="main-content">
            <div class="container">
                <!-- Right Section: Add Pet Form -->
                <div class="right-section">
                    <!-- <h2>Add a New Pet</h2> -->
                    <form class="staff-form" action="" method="POST">
                        <section id="appointments-section" class="appointments-section">
                            <div class="appointments-container">
                                <?php if (!empty($invoices)): ?>
                                    <table class="invoice-table">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Invoice Date</th>
                                                <th>Appointment Date</th>
                                                <th>Pet Name</th>
                                                <th>Service</th>
                                                <th>Total Amount</th>
                                                <th>Status</th>
                                                <th>Paid At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($invoice['InvoiceNumber']) ?></td>
                                            <td><?= htmlspecialchars($invoice['InvoiceDate']) ?></td>
                                            <td><?= htmlspecialchars($invoice['AppointmentDate'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($invoice['PetName'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($invoice['ServiceName'] ?? 'N/A') ?></td>
                                            <td>$<?= number_format($invoice['TotalAmount'], 2) ?></td>
                                            <td>
                                                <?php if ($invoice['Status'] === 'Paid'): ?>
                                                    <span class="status paid">Paid</span>
                                                <?php elseif ($invoice['Status'] === 'Overdue'): ?>
                                                    <span class="status overdue">Overdue</span>
                                                <?php else: ?>
                                                    <span class="status pending">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $invoice['PaidAt'] ? htmlspecialchars($invoice['PaidAt']) : '-' ?></td>
                                            <td>
                                                <button class="download-btn" data-id="<?= $invoice['InvoiceId'] ?>">
                                                    <i class="fas fa-download"></i> Download
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-invoices">No invoices found.</p>
                                <?php endif; ?>
                            </div>
                        </section>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <div class="pagination">
        <a href="?page=<?= max(0, $currentPage - 1) ?>">&laquo; Previous</a>
        <?php for ($i = 0; $i < $totalPages; $i++): ?>
            <?php if ($i == 0 || $i == $totalPages - 1 || abs($i - $currentPage) <= 2): ?>
                <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i + 1 ?></a>
            <?php elseif ($i == 1 || $i == $totalPages - 2): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>">Next &raquo;</a>
    </div>

    <br>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".download-btn").forEach(button => {
                button.addEventListener("click", function () {
                    const invoiceId = this.dataset.id;
                    const downloadUrl = `../../src/download_invoice.php?invoice_id=${invoiceId}`;

                    // ✅ Open download in a new tab
                    window.open(downloadUrl, '_blank');
                });
            });
        });
    </script>

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
    
</body>
</html>