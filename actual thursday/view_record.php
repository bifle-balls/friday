<?php

$id = $_GET['id'] ?? 0;
// Get patient ID
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "oro_va_dental_records"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch the patient record from the database
$sql = "SELECT * FROM patient_registry WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}


// Fetch the appointment history for this patient, ordered by registry date
$appointment_sql = "SELECT * FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC";
$appointment_stmt = $conn->prepare($appointment_sql);
$appointment_stmt->bind_param("i", $id);
$appointment_stmt->execute();
$appointment_result = $appointment_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="view_record.css">
    <title>View Full Record</title>
</head>
<body>

<div class="container">
    <div class="header-and-form">
        <h3>Full Patient Record</h3>
    </div>

    <!-- Patient Information -->
    <div class="record-details">
        <div class="record">
            <div><strong>Registry Date:</strong> <?= $patient['registry_date']; ?></div>
            <div><strong>Name:</strong> <?= $patient['last_name'] . ', ' . $patient['first_name'] . ' ' . $patient['middle_name']; ?></div>
            <div><strong>Birthday:</strong> <?= $patient['birthday']; ?></div>
            <div><strong>Age:</strong> <?= $patient['age']; ?></div>
            <div><strong>Email:</strong> <?= $patient['email']; ?></div>
            <div><strong>Address:</strong> <?= $patient['address']; ?></div>
            <div><strong>Mobile Number:</strong> <?= $patient['mobile_number']; ?></div>
        </div>
    </div>

    <div class="header-and-form">
        <h3>Appointment History</h3>
    </div>
        <?php if ($appointment_result->num_rows > 0): ?>
            <div class="appointment-list">
                <?php while ($appointment = $appointment_result->fetch_assoc()): ?>
                    <div class="appointment-item">
                        <div><strong>Appointment Date:</strong> <span><?= $appointment['appointment_date']; ?></span></div>
                        <div><strong>Start Time:</strong> <span><?= $appointment['appointment_start_time']; ?></span></div>
                        <div><strong>End Time:</strong> <span><?= $appointment['appointment_end_time']; ?></span></div>
                        <div><strong>Services:</strong> <span><?= $appointment['services']; ?></span></div>
                        <div><strong>Partial Denture Services:</strong> <?= $appointment['partial_denture_service']; ?></div>
                        <div><strong>Partial Denture Count:</strong> <?= $appointment['partial_denture_count']; ?></div>
                        <div><strong>Full Denture Services:</strong> <?= $appointment['full_denture_service']; ?></div>
                        <div><strong>Full Denture Ranges:</strong> <?= $appointment['full_denture_range']; ?></div>
                    </div>
                <?php endwhile; ?>

        <?php else: ?>
            <p>No appointment history found.</p>
        <?php endif; ?>
    </div>

    <div class="buttons">
        <button onclick="window.location.href='edit_record.php?id=<?= $id; ?>'">Edit Record</button>
        <button onclick="window.location.href='check_appointment.php'">Back to Appointment List</button>
        <button onclick="window.location.href='add_appointment.php?id=<?= $patient['id']; ?>'">Add New Appointment</button>
    </div>
</div>

</body>
</html>
