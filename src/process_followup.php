<?php
session_start();
require '../config/dbh.inc.php';

if (!isset($_SESSION['UserId'])) {
    $_SESSION['errors']['auth'] = "Unauthorized access.";
    header("Location: staff_login.php");
    exit();
}

$appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
$followup_dates = $_POST['follow_up_dates'] ?? [];
$followup_notes = $_POST['follow_up_notes'] ?? [];

if (!$pet_id || !$appointment_id || empty($followup_dates)) {
    $_SESSION['errors']['missing_data'] = "Missing required information.";
    $_SESSION['form_data'] = $_POST;
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id");
    $stmt->execute([':appointment_id' => $appointment_id, ':pet_id' => $pet_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception("Record not found for the given Appointment ID and Pet ID.");
    }

    $record_id = $record['RecordId'];

    $stmt = $pdo->prepare("
        INSERT INTO FollowUps (RecordId, FollowUpDate, FollowUpNotes) 
        VALUES (:record_id, :followup_date, :followup_notes)
    ");

    $stmtAppointment = $pdo->prepare("
        INSERT INTO Appointments (AppointmentCode, PetId, ServiceId, AppointmentDate, AppointmentTime, Status, Reason) 
        VALUES (:appointment_code, :pet_id, :service_id, :appointment_date, :appointment_time, 'Pending', 'Follow-Up Appointment')
    ");

    foreach ($followup_dates as $index => $followup_date) {
        $followup_note = $followup_notes[$index] ?? null;

        if ($followup_date) {
            // Insert into FollowUps Table
            $stmtFollowUp->execute([
                ':record_id' => $record_id,
                ':followup_date' => $followup_date,
                ':followup_notes' => $followup_note
            ]);

            // Insert into Appointments Table
            $stmtAppointment->execute([
                ':appointment_code' => uniqid('APPT_'),
                ':pet_id' => $pet_id,
                ':service_id' => 1,  // Change this to the correct ServiceId for follow-ups
                ':appointment_date' => $followup_date,
                ':appointment_time' => '09:00:00' // Default time, adjust as needed
            ]);
        }
    }

    $pdo->commit();

    $_SESSION['form_data']['follow_up_dates'] = $followup_dates;
    $_SESSION['form_data']['follow_up_notes'] = $followup_notes;

    $_SESSION['success_follow_up'] = "Follow-Up Schedule saved successfully.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['errors']['db'] = "Database Error: " . $e->getMessage();
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}
?>