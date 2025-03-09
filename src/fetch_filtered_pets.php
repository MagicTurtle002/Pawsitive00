<?php
require __DIR__ . '/../config/dbh.inc.php';

$where_clause = ["Pets.IsConfined = 1"];
$params = [];

// ✅ Search Filter
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(Pets.Name LIKE ? OR Pets.PetCode LIKE ?)";
    array_push($params, $search, $search);
}

// ✅ Date Range Filter
if (!empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $where_clause[] = "Pets.LastVisit BETWEEN ? AND ?";
    array_push($params, $_GET['startDate'], $_GET['endDate']);
}

// ✅ Sorting Filter
$orderBy = "ORDER BY Pets.LastVisit DESC";
if (!empty($_GET['order']) && $_GET['order'] === 'asc') {
    $orderBy = "ORDER BY Pets.LastVisit ASC";
}

// Build WHERE SQL clause
$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

$query = "
    SELECT DISTINCT Pets.PetId, Pets.PetCode, Pets.Name AS PetName, 
           Species.SpeciesName AS PetType, Pets.Weight, Pets.LastVisit AS ConfinementDate
    FROM Pets
    LEFT JOIN Species ON Pets.SpeciesId = Species.Id
    $where_sql
    $orderBy
    LIMIT 10
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$confinedPets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Return JSON response
header('Content-Type: application/json');
echo json_encode($confinedPets);
?>