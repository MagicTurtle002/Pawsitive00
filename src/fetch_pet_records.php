<?php
require '../config/dbh.inc.php';

$pet_id = filter_input(INPUT_GET, 'pet_id', FILTER_VALIDATE_INT);
if (!$pet_id) {
    echo json_encode([]);
    exit();
}

try {
    $query = "
        SELECT 
            pr.RecordId, pr.PetId, pr.ChiefComplaint, pr.OnsetDate, pr.DurationDays, 
            pr.ObservedSymptoms, pr.Appetite, pr.Diet, pr.UrineFrequency, pr.UrineColor, 
            pr.WaterIntake, pr.PainLevel, pr.FecalScore, pr.Environment, pr.MedicationPriorCheckup,

            -- Physical Exam Data
            pe.Pulse, pe.HeartRate, pe.RespiratoryRate, pe.HeartSound, pe.LungSound, 
            pe.MucousMembrane, pe.CapillaryRefillTime, pe.Eyes, pe.Ears, 
            pe.TrachealPinch, pe.AbdominalPalpation, pe.LN, pe.BCS

        FROM PatientRecords pr
        LEFT JOIN PhysicalExams pe ON pr.RecordId = pe.RecordId
        WHERE pr.PetId = :pet_id
        ORDER BY pr.CreatedAt DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($records);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([]);
}
exit();