<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../config/dbh.inc.php';
session_start();

if (!isset($_SESSION['LoggedIn'])) {
    echo "User not logged in.";
    exit;
}

// Define owner ID from session
$owner_id = $_SESSION['OwnerId'] ?? null;
$userName = $_SESSION['OwnerName'];

if (!$owner_id) {
    echo "Owner ID not found.";
    exit;
}

try {
    // Fetch pets linked to the owner
    $query = "
        SELECT 
            p.PetId, 
            p.Name AS PetName, 
            s.SpeciesName AS PetType, 
            p.Gender, 
            p.CalculatedAge AS Age, 
            b.BreedName AS Breed
        FROM Pets p
        INNER JOIN Species s ON p.SpeciesId = s.Id
        INNER JOIN Breeds b ON p.Breed = b.BreedId
        WHERE p.OwnerId = :owner_id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Appointments for the Owner
    $appointmentQuery = "
        SELECT 
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
    ";

    $appointmentStmt = $pdo->prepare($appointmentQuery);
    $appointmentStmt->bindParam(':owner_id', $owner_id, PDO::PARAM_INT);
    $appointmentStmt->execute();
    $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pawsitive</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Poppins', sans-serif; }
</style>
</head>
<body class="bg-gray-100">
  
<header class="bg-[#156f77] py-5 px-6 md:px-20 lg:px-36 fixed w-full top-0 z-50 rounded-b-2xl">
    <nav class="flex justify-between items-center">
        <!-- Logo -->
        <div>
            <img src="../../assets/images/logo/LOGO 2 WHITE.png" 
                 alt="Pawsitive Logo" 
                 class="w-32 md:w-48 lg:w-64 xl:w-72">
        </div>

        <!-- Navigation Links -->
        <ul class="flex gap-4 md:gap-6 lg:gap-8 items-center text-white font-semibold text-sm md:text-base lg:text-lg">
            <li><a href="#home" class="hover:text-gray-300">Home</a></li>
            <li><a href="book_appointment.php" class="hover:text-gray-300">Appointment</a></li>
            <li><a href="pet_add.php" class="hover:text-gray-300">Pets</a></li>
            <li><a href="pet_record.php" class="hover:text-gray-300">Record</a></li>
            <li><a href="invoice.php" class="hover:text-gray-300">Invoice</a></li>
        </ul>

        <!-- Profile Dropdown -->
        <div class="relative">
            <img src="../../assets/images/Icons/User 1.png" alt="Profile Icon" class="w-10 h-10 cursor-pointer">
            <div class="absolute right-0 mt-2 w-48 bg-white text-black shadow-lg rounded-lg hidden">
                <a href="#" class="block px-4 py-2 text-sm hover:bg-gray-200">Settings</a>
                <a href="#" class="block px-4 py-2 text-sm hover:bg-gray-200">Sign Out</a>
            </div>
        </div>
    </nav>
</header>


<main class="container mx-auto px-6 py-10">
  <section class="flex flex-col md:flex-row justify-between items-center bg-white p-10 rounded-xl shadow-md">
    <div>
      <h1 class="text-2xl font-bold">Welcome!</h1>
      <h2 class="text-3xl font-bold text-teal-800"><?= htmlspecialchars($userName); ?></h2>
      <p class="text-gray-500" id="current-date"></p>
    </div>
    <img src="../../assets/images/Icons/Pet pic 1.png" alt="Dog and Cat" class="w-40 md:w-60">
  </section>

  <h2 class="text-xl font-bold mt-10">Your Pets</h2>
  <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
    <?php if (!empty($pets)): ?>
      <?php foreach ($pets as $pet): ?>
        <div class="bg-white p-6 rounded-lg shadow-md flex items-center space-x-4">
          <img src="../../assets/images/Icons/Profile User.png" class="w-16 h-16 rounded-full">
          <div>
            <p class="font-semibold">Name: <?= htmlspecialchars($pet['PetName']); ?></p>
            <p class="text-gray-600">Type: <?= htmlspecialchars($pet['PetType']); ?></p>
            <p class="text-gray-600">Breed: <?= htmlspecialchars($pet['Breed']); ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-red-500">No pets found.</p>
    <?php endif; ?>
  </section>

  <h2 class="text-xl font-bold mt-10">Your Appointments</h2>
  <section class="bg-white p-6 rounded-lg shadow-md mt-4">
    <?php if (!empty($appointments)): ?>
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-teal-600 text-white">
            <th class="p-3">Pet Name</th>
            <th class="p-3">Service</th>
            <th class="p-3">Time</th>
            <th class="p-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $appointment): ?>
            <tr class="border-b">
              <td class="p-3"><?= htmlspecialchars($appointment['PetName']); ?></td>
              <td class="p-3"><?= htmlspecialchars($appointment['Service']); ?></td>
              <td class="p-3"><?= htmlspecialchars($appointment['Time']); ?></td>
              <td class="p-3">
                <span class="px-3 py-1 rounded-lg text-white <?php 
                  switch(strtolower($appointment['Status'])) {
                    case 'confirmed': echo 'bg-blue-500'; break;
                    case 'pending': echo 'bg-yellow-500'; break;
                    case 'done': echo 'bg-green-500'; break;
                    case 'cancelled': echo 'bg-red-500'; break;
                  }
                ?>">
                  <?= htmlspecialchars($appointment['Status']); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-red-500">No appointments found.</p>
    <?php endif; ?>
  </section>
</main>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("current-date").textContent = new Date().toLocaleDateString('en-US', {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
  });
</script>

</body>
</html>
