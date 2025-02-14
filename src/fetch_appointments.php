<?php
require __DIR__ . '/../config/dbh.inc.php';

$where_clause = [];
$params = [];

// Search filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(Pets.Name LIKE ? 
                        OR Appointments.AppointmentCode LIKE ? 
                        OR Services.ServiceName LIKE ? 
                        OR CONCAT(Owners.FirstName, ' ', Owners.LastName) LIKE ?)";
    $params = array_fill(0, 4, $search);
}

// Status filter
if (!empty($_GET['status']) && $_GET['status'] !== "all") {
    $status = $_GET['status'];
    $where_clause[] = "Appointments.Status = ?";
    $params[] = $status;
}

// Date filter
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
        Appointments.Status,
        Services.ServiceName
    FROM Appointments
    INNER JOIN Pets ON Appointments.PetId = Pets.PetId
    INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Services ON Appointments.ServiceId = Services.ServiceId
    $where_sql
    ORDER BY Appointments.AppointmentDate DESC, Appointments.AppointmentId DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by date
$currentDate = null;
if (count($appointments) > 0) {
    foreach ($appointments as $appointment) {
        $appointmentDate = date("F j, Y", strtotime($appointment['AppointmentDate']));

        // Display a new header when the date changes
        if ($currentDate !== $appointmentDate) {
            $currentDate = $appointmentDate;
            echo "<tr class='date-header'><td colspan='4'><strong>{$currentDate}</strong></td></tr>";
        }

        echo "<tr>
                <td>{$appointment['AppointmentId']}</td>
                <td>{$appointment['PetName']}</td>
                <td>{$appointment['Status']}</td>
                <td>{$appointment['ServiceName']}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='4'>No appointments found.</td></tr>";
}