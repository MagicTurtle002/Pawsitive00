<?php
require __DIR__ . '/../config/dbh.inc.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pdo->beginTransaction();
    try {
        foreach ($_POST['permissions'] as $roleId => $permissions) {
            // Remove all existing permissions for the role
            $stmt = $pdo->prepare("DELETE FROM RolePermissions WHERE RoleId = ?");
            $stmt->execute([$roleId]);

            // Insert only the checked permissions
            foreach ($permissions as $permissionId => $value) {
                if ($value == "1") {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO RolePermissions (RoleId, PermissionId) VALUES (?, ?)");
                    $stmt->execute([$roleId, $permissionId]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Permissions updated successfully!";
        header("Location: ../public/settings.php?success=1");
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating permissions: " . $e->getMessage();
    }
}