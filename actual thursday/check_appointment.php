<?php
// Connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oro_va_dental_records";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all appointments, join patient registry for the name
$sql = "
    SELECT 
        a.id AS appointment_id, 
        a.patient_id, 
        p.last_name, 
        p.first_name, 
        a.appointment_date, 
        a.appointment_start_time, 
        a.appointment_end_time, 
        a.services,
        a.partial_denture_service,
        a.partial_denture_count,
        a.full_denture_service,
        a.full_denture_range
    FROM appointments a
    INNER JOIN patient_registry p ON a.patient_id = p.id
";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Patient Appointments</title>
    <link rel="stylesheet" href="appointments.css">
<body>
    <div class="header-and-form">
        <div class="header">
            <h2>Patient Appointment</h2>
            <div class="bruh-button">
                <a href='dashboard.php'> Back to Dashboard</a>
            </div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Appointment Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Services</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['last_name'] . ', ' . $row['first_name']; ?></td>
                        <td><?= $row['appointment_date']; ?></td>
                        <td><?= $row['appointment_start_time']; ?></td>
                        <td><?= $row['appointment_end_time']; ?></td>
                        <td><?= $row['services'] . '</br> ' . $row['partial_denture_service'] . ': ' . $row['partial_denture_count'] . '</br> ' . $row['full_denture_service'] . ': ' . $row['full_denture_range']; ?></td>
                        <td class="actions">
                            <button onclick="window.location.href='view_record.php?id=<?= $row['patient_id']; ?>'">View Full Record</button>
                            <button onclick="window.location.href='edit_appointment.php?id=<?= $row['appointment_id']; ?>'">Edit Appointment</button>
                            <button onclick="window.location.href='add_appointment.php?patient_id=<?= $row['patient_id']; ?>'">Add New Appointment</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No records found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
