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

function calculateAge($birthday) {
    if (empty($birthday)) {
        return null; 
    }
    $birthdayDate = new DateTime($birthday);
    $currentDate = new DateTime();
    $age = $currentDate->diff($birthdayDate)->y;
    return $age;
}

// Capture patient ID (from `patient_registry`) and page for redirection
$patient_id = $_GET['id'] ?? 0;

// Fetch patient data from `patient_registry`
$sql_patient = "SELECT * FROM patient_registry WHERE id = ?";
$stmt_patient = $conn->prepare($sql_patient);
$stmt_patient->bind_param("i", $patient_id);
$stmt_patient->execute();
$result_patient = $stmt_patient->get_result();
$patient = $result_patient->fetch_assoc();
$stmt_patient->close();

if (!$patient) {
    die("<script>alert('Patient not found.'); window.location.href = 'patient_records.php?page=$page';</script>");
}

// Fetch appointments related to the patient from `appointments`
$sql_appointments = "SELECT * FROM appointments WHERE patient_id = ?";
$stmt_appointments = $conn->prepare($sql_appointments);
$stmt_appointments->bind_param("i", $patient_id);
$stmt_appointments->execute();
$result_appointments = $stmt_appointments->get_result();
$appointments = $result_appointments->fetch_all(MYSQLI_ASSOC);
$stmt_appointments->close();

// Pre-fill data for services and denture processing
$selected_services = array_map('trim', explode(',', $patient['services']));
$selected_partial_denture = array_map('trim', explode(',', $patient['partial_denture_service']));
$partial_denture_counts = array_map('trim', explode(',', $patient['partial_denture_count']));
$selected_full_denture = array_map('trim', explode(',', $patient['full_denture_service']));
$full_denture_ranges = array_map('trim', explode(',', $patient['full_denture_range']));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update `patient_registry` table
    $registryDate = $_POST['registryDate'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $givenName = $_POST['givenName'] ?? '';
    $middleName = $_POST['middleName'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $age = calculateAge($birthday); 
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $mobileNumber = $_POST['mobileNumber'] ?? '';


    $services = isset($_POST['services']) ? implode(", ", $_POST['services']) : '';
    $partialDentureStr = implode(", ", $_POST['partial_denture'] ?? []);
    $partialDentureCountStr = implode(", ", $_POST['partial_denture_count'] ?? []);
    $fullDentureStr = implode(", ", $_POST['full_denture'] ?? []);
    $fullDentureRangesStr = implode(", ", $_POST['full_denture_range'] ?? []);

    $update_patient = "UPDATE patient_registry SET 
        registry_date = ?, 
        last_name = ?, 
        first_name = ?, 
        middle_name = ?, 
        dob = ?, 
        age = ?, 
        email = ?, 
        address = ?, 
        mobile_number = ?,
        add_info = ? 
    WHERE id = ?";
    $stmt_update_patient = $conn->prepare($update_patient);
    $stmt_update_patient->bind_param(
        "sssssisssssssssi",
        $registryDate, $lastName, $givenName, $middleName, $dob, $age, $email, $address, 
        $mobileNumber, $patient_id
    );
    $stmt_update_patient->execute();
    $stmt_update_patient->close();

    // Update `appointments` table
    foreach ($_POST['appointments'] as $appointment_id => $appointment_data) {
        $appointmentDate = $appointment_data['appointment_date'];
        $appointmentStartTime = $appointment_data['appointment_start_time'];
        $appointmentEndTime = $appointment_data['appointment_end_time'];
        $appointmentInfo = $appointment_data['add_info'];


        $update_appointment = "UPDATE appointments SET 
            appointment_date = ?, 
            appointment_start_time = ?, 
            appointment_end_time = ?, 
            services = ?, 
            partial_denture_service = ?, 
            partial_denture_count = ?, 
            full_denture_service = ?, 
            full_denture_range = ?, 
            add_info = ? 
        WHERE id = ?";
        $stmt_update_appointment = $conn->prepare($update_appointment);
        $stmt_update_appointment->bind_param(
            "ssssssssssi", $appointmentDate, $appointmentStartTime, $appointmentEndTime, $appointmentInfo, $services, $partialDentureStr, $partialDentureCountStr, $fullDentureStr, 
            $fullDentureRangesStr, $addInfo, $appointment_id,
        );
        $stmt_update_appointment->execute();
        $stmt_update_appointment->close();
    }

    echo "<script>
            alert('Patient and appointments updated successfully.');
            window.location.href = 'view_record.php';
          </script>";
    exit();
}

$conn->close();
?>

