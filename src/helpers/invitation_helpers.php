<?php
function validateStaffInvitation($pdo, $email, $role, $employmentType) {
    $errors = [];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format: $email";
    }

    // Check if email is already registered in `Users`
    $stmt = $pdo->prepare("SELECT UserId FROM Users WHERE Email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email already registered: $email";
    }

    // Check if role exists
    $stmt = $pdo->prepare("SELECT RoleId FROM Roles WHERE RoleName = ?");
    $stmt->execute([$role]);
    $roleId = $stmt->fetchColumn();
    if (!$roleId) {
        $errors[] = "Role not found: $role";
    }

    // Validate employment type
    $validEmploymentTypes = ["Full-Time", "Part-Time", "Contractor"];
    if (!in_array($employmentType, $validEmploymentTypes)) {
        $errors[] = "Invalid employment type: $employmentType";
    }

    // Check for pending invitations
    $stmt = $pdo->prepare("SELECT InvitationId FROM StaffInvitations WHERE Email = ? AND Status = 'Pending'");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Pending invitation already exists for: $email";
    }

    return [$errors, $roleId];
}
?>