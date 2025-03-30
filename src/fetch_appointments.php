<?php
require __DIR__ . '/../config/dbh.inc.php';

$where_clause = [];
$params = [];

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(Pets.Name LIKE ? 
                        OR Appointments.AppointmentCode LIKE ? 
                        OR COALESCE(Services.ServiceName, '') LIKE ? 
                        OR CONCAT(Owners.FirstName, ' ', Owners.LastName) LIKE ?)";
    $params = array_fill(0, 4, $search);
}

if (!empty($_GET['status']) && $_GET['status'] !== "all") {
    $status = $_GET['status'];
    $where_clause[] = "Appointments.Status = ?";
    $params[] = $status;
}

if (!empty($_GET['dateFilter'])) {
    if ($_GET['dateFilter'] === "today") {
        $where_clause[] = "Appointments.AppointmentDate = CURDATE()";
    } elseif ($_GET['dateFilter'] === "this_week") {
        $where_clause[] = "YEARWEEK(Appointments.AppointmentDate, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($_GET['dateFilter'] === "this_month") {
        $where_clause[] = "YEAR(Appointments.AppointmentDate) = YEAR(CURDATE()) AND MONTH(Appointments.AppointmentDate) = MONTH(CURDATE())";
    } elseif ($_GET['dateFilter'] === "custom" && !empty($_GET['startDate']) && !empty($_GET['endDate'])) {
        $where_clause[] = "Appointments.AppointmentDate BETWEEN ? AND ?";
        $params[] = $_GET['startDate'];
        $params[] = $_GET['endDate'];
    }
}

// Build SQL Query
$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
    SELECT 
        Appointments.AppointmentId,
        Owners.OwnerId,
        CONCAT(Owners.FirstName, ' ', Owners.LastName) AS OwnerName,
        Pets.PetCode,
        Pets.PetId,
        Pets.Name AS PetName,
        Appointments.AppointmentDate,
        Appointments.AppointmentTime,
        Appointments.Status,
        Services.ServiceName,
        COALESCE(Services.ServiceName, 'N/A') AS Service
    FROM Appointments
    INNER JOIN Pets ON Appointments.PetId = Pets.PetId
    INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Services ON Appointments.ServiceId = Services.ServiceId
    $where_sql
    ORDER BY Appointments.AppointmentDate DESC, Appointments.AppointmentId DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($appointments);