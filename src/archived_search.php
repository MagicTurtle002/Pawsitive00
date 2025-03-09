<?php
require __DIR__ . '/../config/dbh.inc.php';

$where_clause = ["Pets.IsArchived = 1"];
$params = [];

// ✅ AJAX Search Handling
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(LOWER(Pets.PetCode) LIKE LOWER(?) 
                        OR LOWER(Pets.Name) LIKE LOWER(?) 
                        OR LOWER(CONCAT(Owners.FirstName, ' ', Owners.LastName)) LIKE LOWER(?) 
                        OR LOWER(COALESCE(Species.SpeciesName, '')) LIKE LOWER(?))";
    array_push($params, $search, $search, $search, $search);
}

// ✅ Ensure WHERE SQL is Properly Built
$where_sql = count($where_clause) > 0 ? 'WHERE ' . implode(' AND ', $where_clause) : '';

// ✅ Query for Searching Archived Pets
$query = "
    SELECT DISTINCT
        Owners.OwnerId,
        CONCAT(Owners.FirstName, ' ', Owners.LastName) AS owner_name,
        Owners.Email,
        Owners.Phone,
        Pets.PetId,
        Pets.Name AS PetName,
        Pets.PetCode,
        COALESCE(Species.SpeciesName, 'Unknown') AS PetType
    FROM Owners
    LEFT JOIN Pets ON Pets.OwnerId = Owners.OwnerId
    LEFT JOIN Species ON Pets.SpeciesId = Species.Id
    $where_sql
    ORDER BY Pets.PetCode ASC
    LIMIT 10
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$archivedPets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Return JSON response
header('Content-Type: application/json');
echo json_encode($archivedPets);
?>