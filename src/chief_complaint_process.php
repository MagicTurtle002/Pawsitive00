<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../config/dbh.inc.php';

header('Content-Type: application/json');

// Validate AJAX request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit();
}

// Required parameters
$appointment_id = $_POST['appointment_id'] ?? null;
$pet_id = $_POST['pet_id'] ?? null;
$field_name = $_POST['field_name'] ?? null;
$field_value = $_POST['field_value'] ?? null;

if (!$appointment_id || !$pet_id || !$field_name) {
    echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
    exit();
}

// Sanitize input
$field_value = is_array($field_value) ? implode(', ', array_map('htmlspecialchars', $field_value)) : trim(htmlspecialchars($field_value, ENT_QUOTES));

// Check if record exists
$stmt_check = $pdo->prepare("SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id");
$stmt_check->execute([
    ':appointment_id' => $appointment_id,
    ':pet_id' => $pet_id
]);
$record = $stmt_check->fetch(PDO::FETCH_ASSOC);

try {
    if ($record) {
        // Update only the modified field
        $sql = "UPDATE PatientRecords SET $field_name = :field_value, UpdatedAt = NOW() WHERE AppointmentId = :appointment_id AND PetId = :pet_id";
        $stmt_update = $pdo->prepare($sql);

        if ($stmt_update->execute([
            ':field_value' => $field_value,
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ])) {
            echo json_encode(["status" => "success", "message" => "Field updated successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update field."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Record not found."]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>

<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../config/dbh.inc.php';

$appointment_id = $_POST['appointment_id'] ?? '';
$pet_id = $_POST['pet_id'] ?? '';
$chief_complaint = trim(htmlspecialchars($_POST['chief_complaint'] ?? '', ENT_QUOTES));
$onset_date = !empty($_POST['onset']) ? htmlspecialchars($_POST['onset'], ENT_QUOTES) : null;
$duration_days = $_POST['duration'] ?? '';
$custom_duration = $_POST['custom_duration'] ?? '';
$observed_symptoms = isset($_POST['symptoms']) ? implode(', ', $_POST['symptoms']) : '';
$appetite = htmlspecialchars($_POST['appetite'] ?? '', ENT_QUOTES);
$diet = htmlspecialchars($_POST['diet'] ?? '', ENT_QUOTES);
$custom_diet = htmlspecialchars($_POST['custom_diet'] ?? '', ENT_QUOTES);
$urine_frequency = htmlspecialchars($_POST['frequency'] ?? '', ENT_QUOTES);
$custom_frequency = htmlspecialchars($_POST['custom_frequency'] ?? '', ENT_QUOTES);
$urine_color = htmlspecialchars($_POST['color'] ?? '', ENT_QUOTES);
$custom_color = htmlspecialchars($_POST['custom_color'] ?? '', ENT_QUOTES);
$water_intake = htmlspecialchars($_POST['water_intake'] ?? '', ENT_QUOTES);
$pain_level = !empty($_POST['pain_level']) ? (int)$_POST['pain_level'] : null;
$fecal_score = !empty($_POST['fecal-score']) ? (int)$_POST['fecal-score'] : null;
$environment = htmlspecialchars($_POST['environment'] ?? '', ENT_QUOTES);
$medication_prior = trim(htmlspecialchars($_POST['medication'] ?? '', ENT_QUOTES));
$current_timestamp = date("Y-m-d H:i:s");

if (empty($chief_complaint)) {
    $_SESSION['error_message'] = "Chief complaint is required.";
    header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
    exit();
}
if ($duration_days === "Other") {
    $duration_days = (!empty($custom_duration) && is_numeric($custom_duration) && $custom_duration > 0) ? $custom_duration : null;
}
if ($diet === "Other" && !empty($custom_diet)) {
    $diet = $custom_diet;
}
if ($urine_frequency === "Other" && !empty($custom_frequency)) {
    $urine_frequency = $custom_frequency;
}
if ($urine_color === "Other" && !empty($custom_color)) {
    $urine_color = $custom_color;
}

$stmt_check = $pdo->prepare("SELECT RecordId FROM PatientRecords WHERE AppointmentId = :appointment_id AND PetId = :pet_id");
$stmt_check->execute([
    ':appointment_id' => $appointment_id,
    ':pet_id' => $pet_id
]);
$record = $stmt_check->fetch(PDO::FETCH_ASSOC);

try {
    if ($record) {
        $stmt_update = $pdo->prepare("
            UPDATE PatientRecords
            SET ChiefComplaint = :chief_complaint,
                OnsetDate = :onset_date,
                DurationDays = :duration_days,
                ObservedSymptoms = :observed_symptoms,
                Appetite = :appetite,
                Diet = :diet,
                UrineFrequency = :urine_frequency,
                UrineColor = :urine_color,
                WaterIntake = :water_intake,
                PainLevel = :pain_level,
                FecalScore = :fecal_score,
                Environment = :environment,
                MedicationPriorCheckup = :medication_prior,
                UpdatedAt = :updated_at
            WHERE AppointmentId = :appointment_id AND PetId = :pet_id
        ");

        if (!$stmt_update->execute([
            ':chief_complaint' => $chief_complaint,
            ':onset_date' => $onset_date,
            ':duration_days' => $duration_days,
            ':observed_symptoms' => $observed_symptoms, 
            ':appetite' => $appetite,
            ':diet' => $diet,
            ':urine_frequency' => $urine_frequency,
            ':urine_color' => $urine_color,
            ':water_intake' => $water_intake,
            ':pain_level' => $pain_level,
            ':fecal_score' => $fecal_score,
            ':environment' => $environment,
            ':medication_prior' => $medication_prior,
            ':updated_at' => $current_timestamp,
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id
        ])) {
            print_r($stmt_update->errorInfo());
            exit();
        } else {
            echo "Update successful!<br>";
        }

        $_SESSION['success_message'] = "Chief complaint updated successfully!";
    } else {
        $stmt_insert = $pdo->prepare("
            INSERT INTO PatientRecords (
                AppointmentId, PetId, ChiefComplaint, OnsetDate, DurationDays, ObservedSymptoms,
                Appetite, Diet, UrineFrequency, UrineColor, WaterIntake,
                PainLevel, FecalScore, Environment, MedicationPriorCheckup,
                CreatedAt, UpdatedAt
            ) VALUES (
                :appointment_id, :pet_id, :chief_complaint, :onset_date, :duration_days, :observed_symptoms,
                :appetite, :diet, :urine_frequency, :urine_color, :water_intake,
                :pain_level, :fecal_score, :environment, :medication_prior,
                :created_at, :updated_at
            )
        ");

        if (!$stmt_insert->execute([
            ':appointment_id' => $appointment_id,
            ':pet_id' => $pet_id,
            ':chief_complaint' => $chief_complaint,
            ':onset_date' => $onset_date,
            ':observed_symptoms' => $observed_symptoms,
            ':duration_days' => $duration_days,
            ':appetite' => $appetite,
            ':diet' => $diet,
            ':urine_frequency' => $urine_frequency,
            ':urine_color' => $urine_color,
            ':water_intake' => $water_intake,
            ':pain_level' => $pain_level,
            ':fecal_score' => $fecal_score,
            ':environment' => $environment,
            ':medication_prior' => $medication_prior,
            ':created_at' => $current_timestamp,
            ':updated_at' => $current_timestamp
        ])) {
            echo "SQL INSERT Error: ";
            print_r($stmt_insert->errorInfo());
            exit();
        } else {
            echo "Insert successful!<br>";
        }

        $_SESSION['success_chief_complaint'] = "Chief complaint saved successfully!";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    exit();
}

header("Location: ../public/patient_records.php?appointment_id=$appointment_id&pet_id=$pet_id");
exit();