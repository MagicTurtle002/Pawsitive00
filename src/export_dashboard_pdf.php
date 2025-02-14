<?php
require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php'; // Include TCPDF

use TCPDF;

// =============================
// ✅ Initialize PDF with Branding
// =============================
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Pet Adventure Veterinary Clinic');
$pdf->SetTitle('Dashboard Report');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// ✅ Add Clinic Logo
$logoPath = '../assets/images/logo/LOGO.png';
$pdf->Image($logoPath, 10, 10, 40);
$pdf->Ln(20);

// ✅ Report Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Pet Adventure Veterinary Clinic', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Dashboard Report | Generated on: ' . date('Y-m-d'), 0, 1, 'C');
$pdf->Ln(10);

// =============================
// 📊 Quick Stats
// =============================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'Dashboard Overview', 0, 1, 'C');
$pdf->Ln(5);

$totalAppointmentsQuery = "SELECT COUNT(*) AS total FROM Appointments WHERE AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
$stmt = $pdo->prepare($totalAppointmentsQuery);
$stmt->execute();
$totalAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$totalPetsQuery = "SELECT COUNT(*) AS total FROM Pets WHERE IsArchived = 0";
$stmt = $pdo->prepare($totalPetsQuery);
$stmt->execute();
$totalPets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(90, 8, 'Total Appointments (Last Year)', 1, 0, 'C');
$pdf->Cell(90, 8, 'Total Registered Pets', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(90, 8, $totalAppointments, 1, 0, 'C');
$pdf->Cell(90, 8, $totalPets, 1, 1, 'C');

$pdf->Ln(10);

// =============================
// 📅 Total Appointments per Month
// =============================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'Appointments Per Month (Last Year)', 0, 1, 'C');
$pdf->Ln(5);

$query = "SELECT DATE_FORMAT(AppointmentDate, '%Y-%m') AS Month, COUNT(*) AS Count 
          FROM Appointments 
          WHERE AppointmentDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
          GROUP BY Month
          ORDER BY Month";
$stmt = $pdo->prepare($query);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(90, 8, 'Month', 1, 0, 'C');
$pdf->Cell(90, 8, 'Total Appointments', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 11);
foreach ($appointments as $data) {
    $pdf->Cell(90, 8, $data['Month'], 1, 0, 'C');
    $pdf->Cell(90, 8, $data['Count'], 1, 1, 'C');
}

$pdf->Ln(10);

// =============================
// 🐾 Total Registered Pets
// =============================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'Total Registered Pets by Type', 0, 1, 'C');
$pdf->Ln(5);

$query = "SELECT SpeciesName, COUNT(*) AS Count 
          FROM Pets 
          INNER JOIN Species ON Pets.SpeciesId = Species.Id
          WHERE Pets.IsArchived = 0
          GROUP BY SpeciesName";
$stmt = $pdo->prepare($query);
$stmt->execute();
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(90, 8, 'Pet Type', 1, 0, 'C');
$pdf->Cell(90, 8, 'Total Registered', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 11);
foreach ($pets as $data) {
    $pdf->Cell(90, 8, $data['SpeciesName'], 1, 0, 'C');
    $pdf->Cell(90, 8, $data['Count'], 1, 1, 'C');
}

$pdf->Ln(10);

// =============================
// 💉 Vaccination Records
// =============================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'Vaccination Records', 0, 1, 'C');
$pdf->Ln(5);

$query = "SELECT p.Name AS PetName, v.VaccinationName, v.VaccinationDate, v.Manufacturer 
          FROM PetVaccinations v
          INNER JOIN Pets p ON v.PetId = p.PetId
          ORDER BY v.VaccinationDate DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(50, 8, 'Pet Name', 1, 0, 'C');
$pdf->Cell(50, 8, 'Vaccine', 1, 0, 'C');
$pdf->Cell(40, 8, 'Date', 1, 0, 'C');
$pdf->Cell(50, 8, 'Manufacturer', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 11);
foreach ($vaccinations as $data) {
    $pdf->Cell(50, 8, $data['PetName'], 1, 0, 'C');
    $pdf->Cell(50, 8, $data['VaccinationName'], 1, 0, 'C');
    $pdf->Cell(40, 8, $data['VaccinationDate'], 1, 0, 'C');
    $pdf->Cell(50, 8, $data['Manufacturer'], 1, 1, 'C');
}

// =============================
// 📢 Footer Section
// =============================
$pdf->Ln(15);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 8, 'Pet Adventure Veterinary Clinic | (555) 123-4567 | support@vetpawsitive.com', 0, 1, 'C');
$pdf->Cell(0, 8, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');

// =============================
// ✅ Save and Export PDF
// =============================
$pdf->Output('dashboard_report_' . date('Y-m-d') . '.pdf', 'D');
exit;
?>