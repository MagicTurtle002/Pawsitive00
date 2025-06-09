<?php
require '../config/dbh.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = trim($_POST['role_name']);
    $description = trim($_POST['description']);
    $permissions = $_POST['permissions'] ?? [];

    try {
        $stmt = $pdo->prepare("INSERT INTO Roles (RoleName, Description) VALUES (:role_name, :description)");
        $stmt->execute([':role_name' => $role_name, ':description' => $description]);
        $role_id = $pdo->lastInsertId();

        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO RolePermissions (RoleId, PermissionId) VALUES (:role_id, :permission_id)");
            foreach ($permissions as $permission_id) {
                $stmt->execute([':role_id' => $role_id, ':permission_id' => $permission_id]);
            }
        }

        header('Location: ../public/settings.php?success=1');
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ../public/settings.php?error=1');
    }
}