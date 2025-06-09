<?php
require '../config/dbh.inc.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="staff_invite_template.xlsx"');

// 1️⃣ Fetch Roles from the Database
try {
    $stmt = $pdo->prepare("SELECT RoleName FROM Roles");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $roles = ['Veterinarian', 'Receptionist']; // Fallback example roles
}

// 2️⃣ Employment Types
$employmentTypes = ['Full-Time', 'Part-Time', 'Contractor'];

// 3️⃣ Create Excel Template
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Column Headers
$sheet->setCellValue('A1', 'Staff Email');
$sheet->setCellValue('B1', 'Role (Choose from Valid Roles)');
$sheet->setCellValue('C1', 'Employment Type (Full-Time, Part-Time, Contractor)');

// Example Data
$sheet->setCellValue('A2', 'example1@example.com');
$sheet->setCellValue('B2', $roles[0] ?? 'Veterinarian');  // First role from DB or fallback
$sheet->setCellValue('C2', $employmentTypes[0]);          // Full-Time

$sheet->setCellValue('A3', 'example2@example.com');
$sheet->setCellValue('B3', $roles[1] ?? 'Receptionist');  // Second role from DB or fallback
$sheet->setCellValue('C3', $employmentTypes[1]);          // Part-Time

// 4️⃣ Add Valid Roles (Reference)
$sheet->setCellValue('E1', 'Valid Roles');
foreach ($roles as $index => $role) {
    $sheet->setCellValue('E' . ($index + 2), $role);
}

// 5️⃣ Add Employment Types (Reference)
$sheet->setCellValue('F1', 'Employment Types');
foreach ($employmentTypes as $index => $type) {
    $sheet->setCellValue('F' . ($index + 2), $type);
}

// 6️⃣ Adjust Column Widths
foreach (range('A', 'F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Export the File
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>