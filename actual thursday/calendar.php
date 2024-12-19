<?php
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
// Fetch current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Restrict the year between 2010 and 2050
if ($year < 2010) {
    $year = 2010;
} elseif ($year > 2050) {
    $year = 2050;
}

// Adjust month and year for navigation
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// Calculate the first and last days of the month
$first_day_of_month = "$year-$month-01";
$last_day_of_month = date("Y-m-t", strtotime($first_day_of_month));

// Fetch appointments for the current month
$query = "SELECT appointment_date, appointment_start_time, appointment_end_time, patient_name
          FROM appointments
          WHERE appointment_date BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $first_day_of_month, $last_day_of_month);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[$row['appointment_date']][] = [
        'start_time' => $row['appointment_start_time'],
        'end_time' => $row['appointment_end_time'],
        'name' => $row['patient_name']
    ];
}

// Generate the calendar
function generate_calendar($month, $year, $appointments) {
    $days_in_month = date('t', strtotime("$year-$month-01"));
    $start_day = date('w', strtotime("$year-$month-01"));

    echo '<table>';
    echo '<tr><th>Sunday</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th><th>Saturday</th></tr>';
    echo '<tr>';

    // Fill empty days at the beginning
    for ($i = 0; $i < $start_day; $i++) {
        echo '<td></td>';
    }

    // Fill days with appointment status
    for ($day = 1; $day <= $days_in_month; $day++) {
        $current_date = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);

        if (isset($appointments[$current_date])) {
            // Display a single "Occupied" button for dates with appointments
            echo "<td class='occupied' data-date='$current_date'>";
            echo "$day<br>";
            echo "<button class='appointment-btn' onclick=\"showAppointments('$current_date')\">Occupied</button>";
            echo "</td>";
        } else {
            // Display "Available" button for free dates
            echo "<td class='available' data-date='$current_date'>$day<br><button class='available-btn'>Available</button></td>";
        }

        // Start a new row every 7 days
        if (($day + $start_day) % 7 == 0) {
            echo '</tr><tr>';
        }
    }

    // Fill empty days at the end
    while (($day + $start_day) % 7 != 0) {
        echo '<td></td>';
        $day++;
    }

    echo '</tr>';
    echo '</table>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="calendar.css">
    <title>Appointment Calendar</title>
</head>
<body>
    <div class="calendar-container">
        <h1>Appointment Calendar</h1>
        <div class="navigation">
            <a href="dashboard.php" class="dashboard-btn">Back to Dashboard</a>
            <?php if ($year > 2010 || ($year == 2010 && $month > 1)): ?>
                <a href="?month=<?php echo $month - 1; ?>&year=<?php echo $year; ?>">Previous Month</a>
            <?php endif; ?>
            <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>">Current Month</a>
            <?php if ($year < 2050 || ($year == 2050 && $month < 12)): ?>
                <a href="?month=<?php echo $month + 1; ?>&year=<?php echo $year; ?>">Next Month</a>
            <?php endif; ?>
        </div>
        <h2><?php echo date('F Y', strtotime("$year-$month-01")); ?></h2>
        <?php generate_calendar($month, $year, $appointments); ?>
    </div>

<!-- Dynamic content container -->
<div id="appointment-details" class="appointment-details">
    <p>Select a date to view appointments.</p>
</div>

<script>
// Appointment data passed to JavaScript dynamically
const appointments = <?php echo json_encode($appointments); ?>;

function showAppointments(date) {
    const appointmentContainer = document.getElementById('appointment-details');
    appointmentContainer.innerHTML = ''; // Clear previous content

    if (appointments[date]) {
        const formattedDate = new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        let content = `<h3>${formattedDate} (Date selected at the calendar)</h3>`;
        content += '<table>';
        content += '<thead><tr><th>Patient Name</th><th>Start Time</th><th>End Time</th></tr></thead>';
        content += '<tbody>';

        appointments[date].forEach(appointment => {
            content += `
            <tr>
                <td>${appointment.name}</td>
                <td>${formatTime(appointment.start_time)}</td>
                <td>${formatTime(appointment.end_time)}</td>
            </tr>`;
        });

        content += '</tbody></table>';
        content += '<hr>';
        appointmentContainer.innerHTML = content;
    } else {
        appointmentContainer.innerHTML = `<p>No appointments for ${date}.</p>`;
    }
}

function formatTime(time) {
    const [hour, minute] = time.split(':');
    const isPM = hour >= 12;
    const formattedHour = isPM ? hour - 12 || 12 : hour;
    const period = isPM ? 'PM' : 'AM';
    return `${formattedHour}:${minute} ${period}`;
}
</script>

</body>
</html>
