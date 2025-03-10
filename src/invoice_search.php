<?php
require __DIR__ . '/../config/dbh.inc.php';

header('Content-Type: application/json');

$where_clause = ["i.Status IN ('Pending', 'Overdue', 'Partial Payment')"];
$params = [];

// ✅ AJAX Search Handling
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(LOWER(i.InvoiceNumber) LIKE LOWER(?) 
                        OR LOWER(CONCAT(o.FirstName, ' ', o.LastName)) LIKE LOWER(?) 
                        OR LOWER(p.Name) LIKE LOWER(?))";
    array_push($params, $search, $search, $search);
}

// ✅ Ensure WHERE SQL is Properly Built
$where_sql = count($where_clause) > 0 ? 'WHERE ' . implode(' AND ', $where_clause) : '';

// ✅ Query for Searching Invoices
$query = "
    SELECT i.InvoiceId, i.InvoiceNumber, i.InvoiceDate AS DateIssued, 
           i.TotalAmount, i.Status, a.AppointmentDate AS DueDate, 
           p.Name AS PetName, CONCAT(o.FirstName, ' ', o.LastName) AS OwnerName, 
           s.ServiceName
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    $where_sql
    ORDER BY i.InvoiceDate ASC
";

// ✅ Execute query securely
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($invoices);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>