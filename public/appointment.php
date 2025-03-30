<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/dbh.inc.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers/auth_helpers.php';
require __DIR__ . '/../src/helpers/session_helpers.php';
require __DIR__ . '/../src/helpers/permissions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

checkAuthentication($pdo);
enhanceSessionSecurity();

$userId = $_SESSION['UserId'];
$userName = $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'];
$role = $_SESSION['Role'] ?? 'Role';
$email = $_SESSION['Email'];

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (!hasPermission($pdo, 'View Appointments')) {
    exit("You do not have permission to access this page.");
}

$events = [];
$booked_times_by_date = [];

try {
    $query = "
        SELECT 
            a.AppointmentId, 
            a.AppointmentDate, 
            a.AppointmentTime, 
            p.PetId, 
            p.Name AS PetName, 
            a.Status,
            s.ServiceName
        FROM Appointments AS a
        INNER JOIN Pets AS p ON a.PetId = p.PetId
        INNER JOIN Services AS s ON a.ServiceId = s.ServiceId
        WHERE a.Status IN ('Pending', 'Confirmed', 'Done')  -- Exclude 'Paid'
        ORDER BY a.AppointmentDate ASC
        LIMIT 10;
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $appointments = [];
}

try {
    $events_stmt = $pdo->prepare("
        SELECT 
            Appointments.AppointmentId AS id,
            Services.ServiceName AS title,
            DATE_FORMAT(Appointments.AppointmentDate, '%Y-%m-%d') AS start,
            Appointments.AppointmentTime AS time,
            Appointments.Status AS status,
            CONCAT(Pets.Name, ' (Owner: ', Owners.FirstName, ' ', Owners.LastName, ')') AS description,
            Owners.Email AS owner_email, Owners.FirstName AS owner_name
        FROM Appointments
        INNER JOIN Services ON Appointments.ServiceId = Services.ServiceId
        INNER JOIN Pets ON Appointments.PetId = Pets.PetId
        INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
        ORDER BY Appointments.AppointmentDate ASC, Appointments.AppointmentTime ASC
    ");

    $events_stmt->execute();
    $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        $date = $event['start'] ?? null;
        $time = $event['time'] ?? null;

        if ($date && $time) {
            if (!isset($booked_times_by_date[$date])) {
                $booked_times_by_date[$date] = [];
            }
            $booked_times_by_date[$date][] = $time;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching events: " . $e->getMessage());
    $events = [];
}

$events_json = json_encode($events, JSON_UNESCAPED_SLASHES);

try {
    $services_stmt = $pdo->prepare("SELECT ServiceId, ServiceName FROM Services");
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching services: " . $e->getMessage());
}

try {
    $pets_stmt = $pdo->prepare("
        SELECT 
            Pets.PetId, 
            Pets.Name AS pet_name, 
            Owners.FirstName AS owner_name
        FROM Pets
        INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
        WHERE Pets.IsArchived = 0
    ");
    $pets_stmt->execute();
    $pets = $pets_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pets: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment'])) {
    $petId = filter_input(INPUT_POST, 'PetId', FILTER_VALIDATE_INT);
    $serviceId = filter_input(INPUT_POST, 'ServiceId', FILTER_VALIDATE_INT);
    $appointmentDate = filter_input(INPUT_POST, 'AppointmentDate', FILTER_SANITIZE_SPECIAL_CHARS);

    // Ensure PHP does not misinterpret the date due to time zone differences
    $appointmentDate = date('Y-m-d', strtotime($appointmentDate));
    $appointmentTime = filter_input(INPUT_POST, 'AppointmentTime', FILTER_SANITIZE_SPECIAL_CHARS);

    $today = date('Y-m-d');
    if ($appointmentDate < $today) {
        $_SESSION['message'] = "Invalid date. You cannot select a past date.";
        header("Location: appointment.php");
        exit();
    }

    $checkArchivedStmt = $pdo->prepare("SELECT IsArchived FROM Pets WHERE PetId = ?");
    $checkArchivedStmt->execute([$petId]);
    $pet = $checkArchivedStmt->fetch(PDO::FETCH_ASSOC);

    if ($pet && $pet['IsArchived']) {
        $_SESSION['message'] = "Archived pets cannot book appointments.";
        header("Location: appointment.php");
        exit();
    }

    if ($petId && $serviceId && $appointmentDate && $appointmentTime) {
        $pdo->beginTransaction();

        try {
            $query = "
                INSERT INTO Appointments (PetId, ServiceId, AppointmentDate, AppointmentTime, Status)
                VALUES (:PetId, :ServiceId, :AppointmentDate, :AppointmentTime, 'Pending')
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':PetId' => $petId,
                ':ServiceId' => $serviceId,
                ':AppointmentDate' => $appointmentDate,
                ':AppointmentTime' => $appointmentTime,
            ]);

            $pdo->commit();

            $ownerQuery = "
                SELECT Owners.Email, Owners.FirstName, Pets.Name AS PetName, Services.ServiceName
                FROM Appointments
                INNER JOIN Pets ON Appointments.PetId = Pets.PetId
                INNER JOIN Owners ON Pets.OwnerId = Owners.OwnerId
                INNER JOIN Services ON Appointments.ServiceId = Services.ServiceId
                WHERE Appointments.PetId = ?
            ";
            $ownerStmt = $pdo->prepare($ownerQuery);
            $ownerStmt->execute([$petId]);
            $appointmentData = $ownerStmt->fetch(PDO::FETCH_ASSOC);

            if ($appointmentData) {
                sendEmailNotification(
                    $appointmentData['Email'],
                    $appointmentData['FirstName'],
                    $appointmentData['PetName'],
                    $appointmentData['ServiceName'],
                    $appointmentDate,
                    $appointmentTime
                );
            }

            $_SESSION['message'] = "Appointment successfully added!";
            header("Location: appointment.php");
            exit();
        } catch (PDOException $e) {
            error_log("Error saving appointment: " . $e->getMessage());
            $_SESSION['message'] = "Failed to save appointment. Please try again.";
        }
    } else {
        $_SESSION['message'] = "All fields are required. Please fill out the form.";
    }
}

function sendEmailNotification($email, $ownerName, $petName, $serviceName, $appointmentDate, $appointmentTime)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@vetpawsitive.com';
        $mail->Password = 'Pawsitive3.';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@vetpawsitive.com', 'Pawsitive Veterinary Clinic');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email format: $email");
            return;
        }
        error_log("Sending email to: $email");

        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "Appointment Confirmation for $petName";
        $mail->Body = "
            <p>Hello $ownerName,</p>
            <p>Your appointment for <b>$petName</b> for <b>$serviceName</b> is confirmed.</p>
            <p><strong>Appointment Details:</strong></p>
            <p>Date: <b>$appointmentDate</b></p>
            <p>Time: <b>$appointmentTime</b></p>
            <p>We look forward to seeing you!</p>
            <p>Best regards,</p>
            <p><b>Pawsitive Veterinary Clinic</b></p>
        ";

        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
            error_log("Mail Debug [$level]: $str");
        };

        $mail->send();
        error_log("Email successfully sent to $email");
    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../assets/css/schedule.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var calendarEvents = <?= $events_json ?>;
        var bookedTimesByDate = <?= json_encode($booked_times_by_date) ?>;
        var services = <?= json_encode($services, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
    </script>

</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
        </div>
        <nav>
            <h3>Hello, <?= htmlspecialchars($userName) ?></h3>
            <h4><?= htmlspecialchars($role) ?></h4>
            <br>
            <ul class="nav-links">
                <li><a href="main_dashboard.php">
                        <img src="../assets/images/Icons/Chart 1.png" alt="Chart Icon">Overview</a></li>
                <li><a href="record.php">
                        <img src="../assets/images/Icons/Record 1.png" alt="Record Icon">Record</a></li>
                <li><a href="staff.php">
                        <img src="../assets/images/Icons/Staff 1.png" alt="Contacts Icon">Staff</a></li>
                <li class="active"><a href="appointment.php">
                        <img src="../assets/images/Icons/Schedule 3.png" alt="Schedule Icon">Schedule</a></li>
                <li><a href="invoice_billing_form.php">
                        <img src="../assets/images/Icons/Billing 1.png" alt="Schedule Icon">Invoice</a></li>
            </ul>
        </nav>
        <div class="sidebar-bottom">
            <button onclick="window.location.href='settings.php';">
                <img src="../assets/images/Icons/Settings 1.png" alt="Settings Icon">Settings
            </button>
            <button onclick="window.location.href='logout.php';">
                <img src="../assets/images/Icons/Logout 1.png" alt="Logout Icon">Log out
            </button>
        </div>
    </div>
    <div class="main-content">
        <?php if (!empty($message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    showToast("<?= htmlspecialchars($message); ?>");
                });
            </script>
        <?php endif; ?>
        <header>
            <h1>Schedule</h1>
            <div class="filter-container">
                <label for="statusFilter"><strong>Filter by Status:</strong></label>
                <select id="statusFilter" class="form-select">
                    <option value="all" selected>All</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancel">Cancel</option>
                </select>
            </div>
        </header>
        <hr>
        <br>
        <div id="calendar"></div>
        <div class="appointment-list">
            <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-item" data-id="<?= htmlspecialchars($appointment['AppointmentId']); ?>"
                    data-pet-id="<?= htmlspecialchars($appointment['PetId']); ?>"
                    data-title="<?= htmlspecialchars($appointment['ServiceName']); ?>"
                    data-description="<?= htmlspecialchars($appointment['PetName']); ?>"
                    data-status="<?= htmlspecialchars($appointment['Status']); ?>"
                    data-date="<?= htmlspecialchars($appointment['AppointmentDate']); ?>"
                    data-time="<?= htmlspecialchars($appointment['AppointmentTime']); ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <form action="" method="POST" class="appointment-form" novalidate>
        <div class="right-section">
            <h2>Appointment Section</h2>
            <br>
            <hr>
            <div class="appointment-form-buttons">
                <a href="appointment_list.php" class="btn btn-primary view-all-btn">View All Appointments</a>
            </div>
            <br>
            <form action="appointment.php" method="POST" class="appointment-form">
                <div class="form-group">
                    <label for="PetSearch">Pet:</label>
                    <input type="text" id="PetSearch" name="PetSearch" placeholder="Search for a pet..."
                        autocomplete="off">
                    <input type="hidden" id="PetId" name="PetId">
                    <div id="PetSuggestions" class="suggestions-box"></div>
                </div>
                <br>
                <div class="form-group">
                    <label for="ServiceId">Service:</label>
                    <select name="ServiceId" id="ServiceId" required>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= $service['ServiceId'] ?>"><?= htmlspecialchars($service['ServiceName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <br>
                <div class="form-group">
                    <label for="AppointmentDate">Date:</label>
                    <input type="date" name="AppointmentDate" id="AppointmentDate" required min="<?= date('Y-m-d') ?>">
                </div>
                <br>
                <div class="form-group">
                    <label for="AppointmentTime">Time:</label>
                    <select name="AppointmentTime" id="AppointmentTime" required>
                        <option value="">Select Time</option>
                        <?php
                        $start = strtotime("08:00:00");
                        $end = strtotime("17:00:00");
                        while ($start <= $end) {
                            $displayTime = date("g:i A", $start);
                            $valueTime = date("H:i:s", $start);
                            echo "<option value='$valueTime'>$displayTime</option>";
                            $start = strtotime("+30 minutes", $start);
                        }
                        ?>
                    </select>
                </div>
                <br>
                <button type="submit" name="add_appointment" class="btn btn-primary view-all-btn">Submit
                    Appointment</button>
            </form>
        </div>

        <div id="successToast" class="toast">
            <div class="toast-body"></div>
            <button type="button" class="btn-close"
                onclick="document.getElementById('successToast').classList.remove('show');"></button>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
        <script src="../assets/js/appointment.js?v=<?= time() ?>"></script>
        <script src="../assets/js/update_appointment.js?v=<?= time() ?>"></script>
        <script src="../assets/js/pet_search.js?v=<?= time() ?>"></script>
        <script src="../assets/js/script.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const appointmentItems = document.querySelectorAll(".appointment-item");

                appointmentItems.forEach(item => {
                    const status = item.getAttribute("data-status");

                    if (status === "Done" || status === "Paid" || status === "Declined") {
                        item.classList.add("disabled-appointment");
                        item.style.pointerEvents = "none";
                    } else {
                        item.addEventListener("click", () => {
                            showAppointmentDetails(item.dataset);
                        });
                    }
                });
            });
        </script>
        <script>
            function confirmAppointment(appointmentId) {
                const petElement = document.querySelector(`[data-id='${appointmentId}']`);
                const petId = petElement ? petElement.getAttribute("data-pet-id") : null;

                if (!petId) {
                    console.error("Error: petId is missing for appointmentId:", appointmentId);
                    Swal.fire("Error", "Pet ID is missing. Cannot confirm appointment.", "error");
                    return;
                }

                // Use the function from update_appointment.js
                updateAppointmentStatus(appointmentId, "Confirmed", petId);
            }

            function declineAppointment(appointmentId) {
                const petElement = document.querySelector(`[data-id='${appointmentId}']`);
                const petId = petElement ? petElement.getAttribute("data-pet-id") : null;

                if (!petId) {
                    console.error("Error: petId is missing for appointmentId:", appointmentId);
                    Swal.fire("Error", "Pet ID is missing. Cannot decline appointment.", "error");
                    return;
                }

                updateAppointmentStatus(appointmentId, "Declined", petId);
            }

            function showAppointmentDetails(event) {
                if (event.status === "Declined") {
                    return;
                }

                Swal.fire({
                    title: event.extendedProps.petName || "Appointment Details", // ✅ Set title to pet's name
                    html: `
                            <button class="swal2-close-button" onclick="Swal.close()">×</button>
                            <div style="text-align: left; margin-top: 10px;">
                                <p><strong>Appointment For:</strong> ${event.extendedProps.description || "No Description"}</p>
                                <p><strong>Service:</strong> ${event.title || "No Title"}</p>
                                <p><strong>Date:</strong> ${formatDateWithoutTimezone(event.start)}</p>
                                <p><strong>Time:</strong> ${event.extendedProps.time ? formatTime(event.extendedProps.time) : "No Time"}</p>
                                <p><strong>Status:</strong> <span class="status-badge">${event.extendedProps.status || "Pending"}</span></p>
                            </div>
                        `,
                    showCancelButton: true,
                    showDenyButton: true,
                    showConfirmButton: true,
                    cancelButtonText: "Decline Appointment",
                    denyButtonText: "Reschedule",
                    confirmButtonText: "Confirm Appointment",
                    icon: "info",
                    customClass: {
                        confirmButton: 'swal2-confirm-btn',
                        denyButton: 'swal2-edit-btn',
                        cancelButton: 'swal2-cancel-btn'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        confirmAppointment(event.id);
                    } else if (result.isDenied) {
                        openRescheduleModal({
                            pet: event.extendedProps.description, // 🐛 Ensure pet name is passed
                            service: event.title,
                            date: formatDateWithoutTimezone(event.start), // 🐛 Fix date issue
                            time: event.extendedProps.time,
                            petId: event.extendedProps.petId
                        }, event.id);
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        declineAppointment(event.id);
                    }
                });
            }
        </script>
</body>

</html>