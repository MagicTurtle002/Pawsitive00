<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
require '../vendor/autoload.php'; // ✅ Use a library like Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

if (!isset($_GET['invoice_id'])) {
    die("Invalid request.");
}

$invoiceId = $_GET['invoice_id'];

// ✅ Fetch invoice details
$stmt = $pdo->prepare("
    SELECT i.InvoiceNumber, i.InvoiceDate, i.TotalAmount, i.Status, i.PaidAt,
           a.AppointmentDate, p.Name AS PetName, s.ServiceName
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE i.InvoiceId = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found.");
}

// ✅ Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$html = "
    <h1>Invoice #{$invoice['InvoiceNumber']}</h1>
    <p><strong>Invoice Date:</strong> {$invoice['InvoiceDate']}</p>
    <p><strong>Appointment Date:</strong> {$invoice['AppointmentDate']}</p>
    <p><strong>Pet Name:</strong> {$invoice['PetName']}</p>
    <p><strong>Service:</strong> {$invoice['ServiceName']}</p>
    <p><strong>Total Amount:</strong> $".number_format($invoice['TotalAmount'], 2)."</p>
    <p><strong>Status:</strong> {$invoice['Status']}</p>
    <p><strong>Paid At:</strong> ".($invoice['PaidAt'] ?? 'Not Paid')."</p>
";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ✅ Output as PDF
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=invoice_{$invoice['InvoiceNumber']}.pdf");
echo $dompdf->output();