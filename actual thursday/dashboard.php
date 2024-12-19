<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Get the logged-in user's details
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Database connection
$servername = "localhost";
$usernameDB = "root";
$passwordDB = "";
$dbname = "oro_va_dental_records";

// Create connection
$conn = new mysqli($servername, $usernameDB, $passwordDB, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch today's appointments
$today = date('Y-m-d');
$queryToday = "SELECT patient_name, CONCAT(appointment_start_time, ' - ', appointment_end_time) AS time, services, partial_denture_service, partial_denture_count, full_denture_service, full_denture_range 
               FROM appointments 
               WHERE appointment_date = ?";
$stmtToday = $conn->prepare($queryToday);
$stmtToday->bind_param("s", $today);
$stmtToday->execute();
$resultToday = $stmtToday->get_result();

$scheduleToday = [];
while ($row = $resultToday->fetch_assoc()) {
    $scheduleToday[] = $row;
}
$stmtToday->close();

// Fetch tomorrow's appointments
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$queryTomorrow = "SELECT patient_name, CONCAT(appointment_start_time, ' - ', appointment_end_time) AS time, services, partial_denture_service, partial_denture_count, full_denture_service, full_denture_range  
                  FROM appointments 
                  WHERE appointment_date = ?";
$stmtTomorrow = $conn->prepare($queryTomorrow);
$stmtTomorrow->bind_param("s", $tomorrow);
$stmtTomorrow->execute();
$resultTomorrow = $stmtTomorrow->get_result();

$scheduleTomorrow = [];
while ($row = $resultTomorrow->fetch_assoc()) {
    $scheduleTomorrow[] = $row;
}
$stmtTomorrow->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($role); ?>!</h1>
        <div>
            <a href="patient_registry.html">Patient Registry</a>
            <a href="check_appointment.php">Patient Records</a>
            <a href="calendar.php">Appointment Schedules</a>
            <form method="POST" action="logout.php" style="display: inline;">
                <button class="logout-button">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <h2>Schedule Today (<?php echo date('F j, Y'); ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Time</th>
                    <th>Service</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($scheduleToday) > 0): ?>
                    <?php foreach ($scheduleToday as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['time']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['services']) . '</br> ' 
                            . $appointment['partial_denture_service'] . ': ' . $appointment['partial_denture_count'] . '</br> ' 
                            . $appointment['full_denture_service'] . ': ' . $appointment['full_denture_range']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No appointments scheduled for today.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="containertomo">
        <h2>Schedule Tomorrow (<?php echo date('F j, Y', strtotime('+1 day')); ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Time</th>
                    <th>Service</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($scheduleTomorrow) > 0): ?>
                    <?php foreach ($scheduleTomorrow as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['time']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['services']) . '</br> ' 
                            . $appointment['partial_denture_service'] . ': ' . $appointment['partial_denture_count'] . '</br> ' 
                            . $appointment['full_denture_service'] . ': ' . $appointment['full_denture_range']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No appointments scheduled for tomorrow.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
