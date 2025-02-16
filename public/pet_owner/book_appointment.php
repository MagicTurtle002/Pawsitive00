<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* For pets basic info */
/* For pets basic info */
/* For pets basic info */

session_start();

require '../../config/dbh.inc.php';

if (!isset($_SESSION['LoggedIn'])) {
    header('Location: ../owner_login.php');
    exit();
}

$owner_id = $_SESSION['OwnerId']; // Get the logged-in owner's ID
$user_name = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] . ' ' . $_SESSION['LastName'] : 'Owner';

$currentPage = isset($_GET['page']) ? max(0, (int) $_GET['page']) : 0;
$recordsPerPage = 10; 
$offset = $currentPage * $recordsPerPage;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $petId = $_POST['PetId'] ?? null;
    $serviceId = $_POST['ServiceId'] ?? null;
    $appointmentDate = $_POST['AppointmentDate'] ?? null;
    $appointmentTime = $_POST['AppointmentTime'] ?? null;

    if (!$petId || !$serviceId || !$appointmentDate || !$appointmentTime) {
        $_SESSION['message'] = "All fields are required!";
        $_SESSION['message_type'] = "error";
        header('Location: book_appointment.php');
        exit();
    }

    try {
        // Check if the time slot is already booked
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE AppointmentDate = :appointmentDate AND AppointmentTime = :appointmentTime");
        $checkStmt->execute([
            ':appointmentDate' => $appointmentDate,
            ':appointmentTime' => $appointmentTime
        ]);
        $isBooked = $checkStmt->fetchColumn();

        if ($isBooked) {
            $_SESSION['message'] = "The selected time slot is already booked. Please choose a different time.";
            $_SESSION['message_type'] = "error";
            header('Location: book_appointment.php');
            exit();
        }

        // ✅ Insert appointment
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO Appointments (PetId, ServiceId, AppointmentDate, AppointmentTime, Status)
            VALUES (:petId, :serviceId, :appointmentDate, :appointmentTime, 'Pending')
        ");
        $stmt->execute([
            ':petId' => $petId,
            ':serviceId' => $serviceId,
            ':appointmentDate' => $appointmentDate,
            ':appointmentTime' => $appointmentTime
        ]);
        $pdo->commit();

        $_SESSION['message'] = "Appointment successfully booked!";
        $_SESSION['message_type'] = "success";
        header('Location: book_appointment.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error: Could not book the appointment. Please try again.";
        $_SESSION['message_type'] = "error";
        header('Location: book_appointment.php');
        exit();
    }
}

try {
    $services_stmt = $pdo->prepare("SELECT ServiceId, ServiceName FROM Services");
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching services: " . $e->getMessage());
}

try {
    // Query to fetch pets linked to the logged-in owner
    $query = "SELECT 
        Pets.PetId, 
        Pets.Name AS pet_name, 
        Pets.SpeciesId, 
        Pets.Gender, 
        Pets.CalculatedAge, 
        Pets.Breed
    FROM 
        Pets 
    WHERE 
        Pets.OwnerId = :OwnerId";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':OwnerId', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $appointmentQuery = "SELECT 
        a.AppointmentId, 
        p.Name AS PetName, 
        s.ServiceName AS Service, 
        a.AppointmentTime AS Time, 
        a.Status
    FROM Appointments a
    INNER JOIN Pets p ON a.PetId = p.PetId
    INNER JOIN Services s ON a.ServiceId = s.ServiceId
    WHERE p.OwnerId = :owner_id
    ORDER BY a.AppointmentTime DESC
    LIMIT :offset, :recordsPerPage";

    $appointmentStmt = $pdo->prepare($appointmentQuery);
    $appointmentStmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $appointmentStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $appointmentStmt->bindParam(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
    $appointmentStmt->execute();
    $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);

    $bookedTimesQuery = "
    SELECT AppointmentDate AS booked_date, 
           TIME(AppointmentTime) AS booked_time 
    FROM Appointments";

    $bookedTimesStmt = $pdo->prepare($bookedTimesQuery);
    $bookedTimesStmt->execute();
    $bookedTimes = $bookedTimesStmt->fetchAll(PDO::FETCH_ASSOC);

    $bookedTimesByDate = [];
    foreach ($bookedTimes as $bt) {
        $bookedTimesByDate[$bt['booked_date']][] = $bt['booked_time'];
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$countQuery = "SELECT COUNT(*) FROM Appointments a
               INNER JOIN Pets p ON a.PetId = p.PetId
               WHERE p.OwnerId = :owner_id";

$countStmt = $pdo->prepare($countQuery);
$countStmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
$countStmt->execute();
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pawsitive | Appointment</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/logo/LOGO.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/book_appointment.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const bookedTimesByDate = <?= json_encode($bookedTimesByDate, JSON_PRETTY_PRINT | JSON_HEX_TAG); ?>;
        console.log("Booked Times Data:", bookedTimesByDate); // ✅ Add here
    </script>
</head>

<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php">
                    <img src="../../assets/images/logo/LOGO 2 WHITE.png" alt="Pawsitive Logo">
                </a>
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="book_appointment.php" class="active">Appointment</a></li>
                <li><a href="pet_add.php">Pets</a></li>
                <li><a href="pet_record.php">Record</a></li>
                <li><a href="invoice.php">Invoice</a></li>
            </ul>
            <div class="profile-dropdown">
                <img src="../../assets/images/Icons/User 1.png" alt="Profile Icon" class="profile-icon">
                <div class="dropdown-content">
                    <a href="settings.php"><img src="../../assets/images/Icons/Settings 2.png" alt="Settings">Settings</a>
                    <a href=""><img src="../../assets/images/Icons/Sign out.png" alt="Sign Out">Sign Out</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-text">
                <h1>Appointments</h1>
            </div>
        </section>
        <div class="main-content">
            <div class="container">
                <!-- Right Section: Add Pet Form -->
                <div class="right-section">
                    <!-- <h2>Add a New Pet</h2> -->
                    <form class="staff-form" action="add_pet.php" method="POST">
                        <section id="appointments-section" class="appointments-section">
                            <h2 class="section-headline">Your Booked Appointments</h2>
                            <div class="appointments-container">
                                <?php if (!empty($appointments)): ?>
                                    <table class="appointments-table">
                                        <thead>
                                            <tr>
                                                <th>Pet Name</th>
                                                <th>Service</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $appointment): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($appointment['PetName']); ?></td>
                                                    <td><?= htmlspecialchars($appointment['Service']); ?></td>
                                                    <td><?= htmlspecialchars($appointment['Time']); ?></td>
                                                    <td>
                                                        <span class="status <?= strtolower($appointment['Status']); ?>">
                                                            <?= htmlspecialchars($appointment['Status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="no-appointments-text">No appointments found.</p>
                                <?php endif; ?>
                            </div>
                        </section>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <main>
        <div class="main-content">
            <div class="container">
                <?php if (isset($_SESSION['message'])): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            Swal.fire({
                                icon: '<?= $_SESSION['message_type'] === "success" ? "success" : "error" ?>',
                                title: '<?= $_SESSION['message'] ?>',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        });
                    </script>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>

                <!-- Right Section: Add Pet Form -->
                <div class="right-section">
                    <!-- <h2>Add a New Pet</h2> -->
                    <form class="staff-form" action="book_appointment.php" method="POST" novalidate>
                    <h2 class="section-headline">Book an Appointment</h2>
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-row">
                            <div class="input-container">
                                <label for="PetId">Pet Name:<span class="required-asterisk">*</span></label>
                                <select id="PetId" name="PetId" required>
                                    <option value="">Select pet</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?= htmlspecialchars($pet['PetId']) ?>">
                                            <?= htmlspecialchars($pet['pet_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="input-container">
                                <label for="Service">Service:<span class="required-asterisk">*</span></label>
                                <select name="ServiceId" id="ServiceId" required>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= $service['ServiceId'] ?>">
                                            <?= htmlspecialchars($service['ServiceName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="input-container">
                                <label for="AppointmentDate">Date:<span class="required-asterisk">*</span></label>
                                <input type="date" id="AppointmentDate" name="AppointmentDate" min="" required>
                            </div>

                            <div class="input-container">
                                <label for="AppointmentTime">Time:<span class="required-asterisk">*</span></label>
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
                        </div>

                        <div class="form-buttons">
                            <!-- <button type="button" class="cancel-btn" onclick="window.location.href='../index.html'">Cancel</button>-->
                            <button type="submit" class="regowner-btn">Submit</button>
                        </div>
                    </form>
                </div>
    </main>

    <div class="calendar-container">
        <div class="calendar-header">
            <button id="prevMonth">&lt;</button>
            <h2 id="currentMonthYear"></h2>
            <button id="nextMonth">&gt;</button>
        </div>
        <div id="calendar"></div>
    </div>

    <div class="pagination">
        <a href="?page=<?= max(0, $currentPage - 1) ?>" class="<?= $currentPage == 0 ? 'disabled' : '' ?>">&laquo; Previous</a>
        
        <?php for ($i = 0; $i < $totalPages; $i++): ?>
            <?php if ($i == 0 || $i == $totalPages - 1 || abs($i - $currentPage) <= 2): ?>
                <a href="?page=<?= $i ?>" <?= $i == $currentPage ? 'class="active"' : '' ?>><?= $i + 1 ?></a>
            <?php elseif ($i == 1 || $i == $totalPages - 2): ?>
                <span>...</span>
            <?php endif; ?>
        <?php endfor; ?>
        
        <a href="?page=<?= min($totalPages - 1, $currentPage + 1) ?>" class="<?= $currentPage >= $totalPages - 1 ? 'disabled' : '' ?>">Next &raquo;</a>
    </div>

    <br>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const profileIcon = document.querySelector('.profile-icon');
            const dropdownContent = document.querySelector('.dropdown-content');

            profileIcon.addEventListener('click', function () {
                dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.addEventListener('click', function (event) {
                if (!event.target.matches('.profile-icon')) {
                    if (dropdownContent.style.display === 'block') {
                        dropdownContent.style.display = 'none';
                    }
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const timeSelect = document.getElementById('AppointmentTime');

            function generateTimeSlots(startTime, endTime, interval) {
                const start = new Date(`1970-01-01T${startTime}:00`);
                const end = new Date(`1970-01-01T${endTime}:00`);

                while (start <= end) {
                    const hours = start.getHours();
                    const minutes = start.getMinutes();
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    const formattedHours = hours % 12 === 0 ? 12 : hours % 12;
                    const formattedMinutes = minutes < 10 ? `0${minutes}` : minutes;

                    const timeString = `${formattedHours}:${formattedMinutes} ${ampm}`;

                    const option = document.createElement('option');
                    option.value = timeString;
                    option.textContent = timeString;
                    timeSelect.appendChild(option);

                    start.setMinutes(start.getMinutes() + interval);
                }
            }

            generateTimeSlots('08:00', '17:00', 30);
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarElement = document.getElementById('calendar');
        const currentMonthYearElement = document.getElementById('currentMonthYear');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        const dateInput = document.getElementById('AppointmentDate');
        
        let today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();

        function generateCalendar(month, year) {
            calendarElement.innerHTML = '';

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            currentMonthYearElement.textContent = `${monthNames[month]} ${year}`;

            // Days of the week labels
            const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            daysOfWeek.forEach(day => {
                const dayLabel = document.createElement('div');
                dayLabel.textContent = day;
                dayLabel.style.fontWeight = 'bold';
                calendarElement.appendChild(dayLabel);
            });

            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                calendarElement.appendChild(emptyCell);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('calendar-day');
                dayElement.textContent = day;

                // Highlight today's date
                if (
                    day === today.getDate() &&
                    month === today.getMonth() &&
                    year === today.getFullYear()
                ) {
                    dayElement.classList.add('today');
                }

                // Set click event for selecting date
                dayElement.addEventListener('click', function () {
                    let selectedDate = new Date(year, month, day);
                    let todayWithoutTime = new Date();
                    todayWithoutTime.setHours(0, 0, 0, 0); // Set time to midnight for proper comparison

                    // Correct date formatting to YYYY-MM-DD
                    let formattedDate = `${selectedDate.getFullYear()}-${String(selectedDate.getMonth() + 1).padStart(2, '0')}-${String(selectedDate.getDate()).padStart(2, '0')}`;

                    // Allow selecting today's date but prevent past dates
                    if (selectedDate < todayWithoutTime) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'You cannot select a past date.',
                        });
                        return;
                    }

                    dateInput.value = formattedDate; // Update the input field with the selected date
                });

                calendarElement.appendChild(dayElement);
            }
        }

        function goToNextMonth() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            generateCalendar(currentMonth, currentYear);
        }

        function goToPrevMonth() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            generateCalendar(currentMonth, currentYear);
        }

        prevMonthBtn.addEventListener('click', goToPrevMonth);
        nextMonthBtn.addEventListener('click', goToNextMonth);

        generateCalendar(currentMonth, currentYear);
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const dateInput = document.getElementById('AppointmentDate');

        if (dateInput) {
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Ensure time is set to start of the day
            const formattedDate = today.toISOString().split('T')[0]; // Format: YYYY-MM-DD

            dateInput.setAttribute('min', formattedDate); // Prevent selecting past dates

            // Prevent user from manually inputting past dates
            dateInput.addEventListener('change', function () {
                const selectedDate = new Date(this.value);
                selectedDate.setHours(0, 0, 0, 0);

                if (selectedDate < today) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Date',
                        text: 'You cannot select a past date!',
                    });

                    this.value = ''; // Reset to empty if invalid
                }
            });
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
</body>
</html>