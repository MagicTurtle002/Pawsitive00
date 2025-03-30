<?php
require '../config/dbh.inc.php';
require __DIR__ . '/../src/helpers/permissions.php'; // ✅ Ensure permission functions are included

session_start();

// ✅ Get user permissions
$canManageStaff = hasPermission($pdo, 'Manage Staff'); // Check if user has 'Manage Staff' permission

$where_clause = [];
$params = [];

// ✅ Search Logic
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clause[] = "(LOWER(Users.FirstName) LIKE LOWER(?) 
                        OR LOWER(Users.LastName) LIKE LOWER(?) 
                        OR LOWER(Roles.RoleName) LIKE LOWER(?))";
    array_push($params, $search, $search, $search);
}

// ✅ Build WHERE Clause
$where_sql = !empty($where_clause) ? 'WHERE ' . implode(' AND ', $where_clause) : '';

// ✅ Query to Fetch Staff
$query = "
    SELECT Users.StaffCode,
           CONCAT(Users.FirstName, ' ', Users.LastName) AS name,
           Users.Email,
           Users.EmploymentType,
           Users.PhoneNumber,
           Roles.RoleName AS role,
           Users.Status
    FROM Users
    LEFT JOIN UserRoles ON Users.UserId = UserRoles.UserId
    LEFT JOIN Roles ON UserRoles.RoleId = Roles.RoleId
    $where_sql
    ORDER BY Users.FirstName ASC
    LIMIT 10
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Return JSON Response
header('Content-Type: application/json');
echo json_encode([
    "staff" => $staff,
    "canManageStaff" => $canManageStaff  // ✅ Include permission in response
]);
?>
