<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../config/dbh.inc.php';
require '../vendor/autoload.php'; // ✅ Using Dompdf for PDF generation

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
           a.AppointmentDate, p.Name AS PetName, s.ServiceName, o.FirstName, o.LastName, o.Email
    FROM Invoices i
    INNER JOIN Appointments a ON i.AppointmentId = a.AppointmentId
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Owners o ON p.OwnerId = o.OwnerId
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
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .invoice-container { max-width: 700px; margin: auto; padding: 20px; border: 1px solid #ddd; }
        .header { text-align: center; }
        .header img { max-width: 150px; }
        .header h1 { margin: 5px 0; }
        .invoice-details { width: 100%; margin-top: 20px; }
        .invoice-details td { padding: 8px; border: 1px solid #ddd; }
        .footer { margin-top: 30px; text-align: center; font-size: 14px; }
        .total { font-weight: bold; color: #D35400; }
    </style>
</head>
<body>
    <div class='invoice-container'>
        <div class='header'>
            <img src='https://yourwebsite.com/logo.png' alt='Company Logo'>
            <h1>Pet Adventure Veterinary Clinic</h1>
            <p>123 Pet Street, Animal City, AC 56789 | (555) 123-4567</p>
        </div>

        <h2>Invoice #{$invoice['InvoiceNumber']}</h2>
        <p><strong>Invoice Date:</strong> {$invoice['InvoiceDate']}</p>
        <p><strong>Appointment Date:</strong> {$invoice['AppointmentDate']}</p>
        <p><strong>Customer:</strong> {$invoice['FirstName']} {$invoice['LastName']} ({$invoice['Email']})</p>
        <p><strong>Pet Name:</strong> {$invoice['PetName']}</p>

        <table class='invoice-details'>
            <tr>
                <td><strong>Service</strong></td>
                <td>{$invoice['ServiceName']}</td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td>{$invoice['Status']}</td>
            </tr>
            <tr class='total'>
                <td><strong>Total Amount</strong></td>
                <td>$" . number_format($invoice['TotalAmount'], 2) . "</td>
            </tr>
            <tr>
                <td><strong>Paid At</strong></td>
                <td>" . ($invoice['PaidAt'] ? $invoice['PaidAt'] : 'Not Paid') . "</td>
            </tr>
        </table>

        <div class='footer'>
            <p>Thank you for trusting Pet Adventure Veterinary Clinic!</p>
            <p>For any inquiries, contact us at support@vetpawsitive.com</p>
        </div>
    </div>
</body>
</html>
";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ✅ Output as PDF (force download)
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=invoice_{$invoice['InvoiceNumber']}.pdf");
echo $dompdf->output();