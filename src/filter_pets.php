<?php
require_once __DIR__ . '/../config/dbh.inc.php';

header('Content-Type: application/json');

// Validate sorting order
$order = isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc']) ? $_GET['order'] : 'desc';

// Filters (expecting multiple checkboxes)
$filters = $_GET['filter'] ?? [];
$search = isset($_GET['search']) ? "%" . $_GET['search'] . "%" : null;

$whereClauses = ["Pets.IsArchived = 0", "Pets.IsConfined = 0"];
$params = [];

// Allowed filter mappings
$allowedFilters = [
    "pet_name" => "Pets.Name",
    "pet_service" => "Services.ServiceName",
    "owner_name" => "Owners.FirstName"
];

// Apply selected filters
if (!empty($filters)) {
    $filterConditions = [];
    foreach ($filters as $filter) {
        if (isset($allowedFilters[$filter])) {
            $filterConditions[] = "{$allowedFilters[$filter]} LIKE ?";
            $params[] = $search;
        }
    }
    if (!empty($filterConditions)) {
        $whereClauses[] = "(" . implode(" OR ", $filterConditions) . ")";
    }
}

$whereSQL = count($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Fetch filtered data
$query = "
SELECT 
    Pets.PetId, Pets.Name AS PetName, Pets.PetCode, Species.SpeciesName AS PetType, Pets.Status AS PetStatus,
    Owners.OwnerId, CONCAT(Owners.FirstName, ' ', Owners.LastName) AS OwnerName, Owners.Email, Owners.Phone,
    (SELECT MAX(Appointments.AppointmentDate) FROM Appointments WHERE Appointments.PetId = Pets.PetId) AS LastVisit,
    (SELECT MIN(Appointments.AppointmentDate) FROM Appointments WHERE Appointments.PetId = Pets.PetId) AS NextVisit
FROM Pets
INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
LEFT JOIN Species ON Pets.SpeciesId = Species.Id
LEFT JOIN Appointments ON Pets.PetId = Appointments.PetId
LEFT JOIN Services ON Appointments.ServiceId = Services.ServiceId
$whereSQL
ORDER BY Pets.Name $order
LIMIT 10;
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($pets);