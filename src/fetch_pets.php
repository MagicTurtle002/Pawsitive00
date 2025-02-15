<?php
require_once __DIR__ . '/../config/dbh.inc.php';

if (isset($_GET['query'])) {
    $searchTerm = '%' . $_GET['query'] . '%';

    $query = "SELECT Pets.PetId, Pets.Name AS pet_name, Owners.FirstName AS owner_name 
              FROM Pets 
              INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId 
              WHERE Pets.IsArchived = 0 AND Pets.Name LIKE ? 
              LIMIT 10";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$searchTerm]);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($pets);
}
?>