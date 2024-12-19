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

// Function to calculate age based on DOB
function calculateAge($birthday) {
    if (empty($birthday)) {
        return null; 
    }
    $birthdayDate = new DateTime($birthday);
    $currentDate = new DateTime();
    $age = $currentDate->diff($birthdayDate)->y;
    return $age;
}

// Collect form data
$registryDate = $_POST['registryDate'] ?? '';
$lastName = $_POST['lastName'] ?? '';
$givenName = $_POST['givenName'] ?? '';
$middleName = $_POST['middleName'] ?? '';
$birthday = $_POST['birthday'] ?? '';
$age = calculateAge($birthday); 
$email = $_POST['email'] ?? '';
$address = $_POST['address'] ?? '';
$mobileNumber = $_POST['mobileNumber'] ?? '';
$appointmentDate = $_POST['appointmentDate'] ?? '';
$appointmentStartTime = $_POST['appointmentStartTime'] ?? '';  
$appointmentEndTime = $_POST['appointmentEndTime'] ?? '';  
$addInfo = $_POST['addInfo'] ?? '';

// Handle services
$services = isset($_POST['services']) ? implode(", ", $_POST['services']) : '';

// Handle partial denture data
$partialDenture = [];
$partialDentureCount = [];
if (isset($_POST['partial_denture'])) {
    foreach ($_POST['partial_denture'] as $key => $value) {
        if (isset($_POST["partial_denture"][$key . "_pontic_count"])) {
            $count = $_POST["partial_denture"][$key . "_pontic_count"];
            $partialDenture[] = $value;
            $partialDentureCount[] = $count;
        }
    }
}
$partialDentureStr = implode(", ", $partialDenture);
$partialDentureCountStr = implode(", ", $partialDentureCount);

// Handle full denture data
$fullDenture = [];
$fullDentureRanges = [];
if (isset($_POST['full_denture'])) {
    foreach ($_POST['full_denture'] as $key => $value) {
        if (isset($_POST["full_denture"][$key . "_range"])) {
            $range = $_POST["full_denture"][$key . "_range"];
            $fullDenture[] = $value;
            $fullDentureRanges[] = $range;
        }
    }
}
$fullDentureStr = implode(", ", $fullDenture);
$fullDentureRangesStr = implode(", ", $fullDentureRanges);

// Check for duplicate patient
$sqlCheckName = "SELECT * FROM patient_registry WHERE last_name = ? AND first_name = ? AND middle_name = ?";
$stmtCheckName = $conn->prepare($sqlCheckName);
$stmtCheckName->bind_param("sss", $lastName, $givenName, $middleName);
$stmtCheckName->execute();
$resultNameCheck = $stmtCheckName->get_result();

if ($resultNameCheck->num_rows > 0) {
    // Patient exists
    $patient = $resultNameCheck->fetch_assoc();
    $patient_id = $patient['id'];

    echo "<script>
        if (confirm('There\'s already a record of this patient. Do you want to add an appointment instead?')) {
            window.location.href = 'add_appointment.php?patient_id={$patient_id}';
        } else {
            alert('You cannot create multiple records for the same patient.');
            window.location.href = 'patient_registry.html';
        }
    </script>";
    exit();
} else {
    // Insert new patient into patient_registry
    $sql = "INSERT INTO patient_registry (
        registry_date, last_name, first_name, middle_name, birthday, age, email, address, mobile_number
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssisss", $registryDate, $lastName, $givenName, $middleName, $birthday, $age, $email, $address, $mobileNumber);

    if ($stmt->execute()) {
        // Retrieve patient_id
        $patient_id = $conn->insert_id;
    } else {
        echo "Error: " . $stmt->error;
        exit();
    }
}

/// Step 1: Check if the appointment date already exists
$sqlCheckDate = "SELECT * FROM appointments WHERE appointment_date = ?";
$stmtCheckDate = $conn->prepare($sqlCheckDate);
$stmtCheckDate->bind_param("s", $appointmentDate);
$stmtCheckDate->execute();
$result = $stmtCheckDate->get_result();

// Step 2: Check for time conflicts if the appointment date exists
$timeConflict = false;
if ($result->num_rows > 0) {
    // Check if the appointment time overlaps with an existing appointment on the same date
    while ($row = $result->fetch_assoc()) {
        $existingStartTime = $row['appointment_start_time'];
        $existingEndTime = $row['appointment_end_time'];

        // Allow consecutive appointments (one ends exactly when the next one starts)
        if (($appointmentStartTime >= $existingStartTime && $appointmentStartTime < $existingEndTime) || 
            ($appointmentEndTime > $existingStartTime && $appointmentEndTime <= $existingEndTime) || 
            ($appointmentStartTime == $existingEndTime)) {
            // Conflict found
            $timeConflict = true;
            break; // Exit loop once conflict is detected
        }
    }
}

// If conflict is found, show error and stop further processing
if ($timeConflict) {
    echo "<script>alert('This appointment time overlaps with an existing appointment. Please choose another time.'); window.location.href = 'patient_registry.html';</script>";
    exit();
}


// Insert appointment into appointments
$patientName = "$lastName, $givenName $middleName"; // Concatenate name
$appointmentSql = "INSERT INTO appointments (
    patient_id, patient_name, appointment_date, appointment_start_time, appointment_end_time, 
    services, partial_denture_service, partial_denture_count, full_denture_service, full_denture_range, add_info
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmtAppointment = $conn->prepare($appointmentSql);
$stmtAppointment->bind_param(
    "issssssssss", 
    $patient_id, $patientName, $appointmentDate, $appointmentStartTime, $appointmentEndTime, 
    $services, $partialDentureStr, $partialDentureCountStr, $fullDentureStr, $fullDentureRangesStr, $addInfo
);

if ($stmtAppointment->execute()) {
    echo "<script>alert('Patient and appointment registered successfully.'); window.location.href = 'patient_registry.html';</script>";
} else {
    echo "Error: " . $stmtAppointment->error;
}

// Close connections
$stmtCheckName->close();
$stmt->close();
$stmtCheckTime->close();
$stmtAppointment->close();
$conn->close();

?>
