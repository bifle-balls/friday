<?php

// Check if patient_id is passed correctly
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    die("No patient ID found in URL.");
}

// Get the patient_id from the URL
$patient_id = $_GET['patient_id'];

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

// Fetch the patient record to ensure we're adding an appointment for a valid patient
$sql = "SELECT * FROM patient_registry WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();

// Check if the query was successful
if ($stmt->error) {
    die("Query failed: " . $stmt->error);
}

$result = $stmt->get_result();
$patient = $result->fetch_assoc();

// Check if the patient exists
if (!$patient) {
    die("No patient found for ID: $patient_id");
}

// If the request method is POST, process the form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect form data
    $appointmentDate = $_POST['appointmentDate'] ?? '';
    $appointmentStartTime = $_POST['appointmentStartTime'] ?? '';  
    $appointmentEndTime = $_POST['appointmentEndTime'] ?? '';  
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

    // Step 1: Check if the appointment date already exists
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

    // Insert the new appointment for the patient
    $insertSQL = "INSERT INTO appointments (patient_id, appointment_date, appointment_start_time, appointment_end_time, services, 
                  partial_denture_service, partial_denture_count, full_denture_service, full_denture_range, patient_name) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Get the patient's full name
    $patientFullName = $patient['last_name'] . ', ' . $patient['first_name'] . ' ' . $patient['middle_name'];

    $stmtInsert = $conn->prepare($insertSQL);
    $stmtInsert->bind_param("isssssssss", $patient_id, $appointmentDate, $appointmentStartTime, $appointmentEndTime, 
        $services, $partialDentureStr, $partialDentureCountStr, $fullDentureStr, $fullDentureRangesStr, $patientFullName);

    if ($stmtInsert->execute()) {
        echo "<script>alert('Appointment added successfully.'); window.location.href='check_appointment.php';</script>";
    } else {
        echo "<script>alert('Error adding appointment.');</script>";
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="patient_registry.css">
    <title>Add Appointment</title>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Add Appointment</h1>
            <div class="bruh-button">
            </div>
        </div>
        <form method="post">
            <div class="form-group">
                <label for="fullName">Full Name:</label>
                <input type="text" id="fullName" name="fullName" value="<?= htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . $patient['middle_name']); ?>" readonly>
            </div>

            <div class="apt-section">
                <div class="form-group">
                    <label for="appointmentDate">Appointment Schedule:</label>
                    <input type="date" id="appointmentDate" name="appointmentDate" required>
                </div>

                <div class="form-group">
                    <label for="startTime">Start Time:</label>
                    <input type="time" id="appointmentStartTime" name="appointmentStartTime" required min="13:00" max="17:00">
                    <p>Minimum is 1:00 PM</p>
                </div>
                <div class="form-group">
                    <label for="endTime">End Time:</label>
                    <input type="time" id="appointmentEndTime" name="appointmentEndTime" required min="13:30" max="17:30">
                    <p>Maximum is 5:30 PM</p>
                </div>                
            </div>          

            <div class="form-group">
                <label for="add_info">Additional Information:</label>
                <textarea id="add_info" name="add_info"></textarea>
            </div>

            <h2>Services:</h2>
            <table border="1" cellpadding="10">
                <thead>
                    <tr>
                        <th>Diagnosis</th>
                        <th>Periodontics (Oral Prophylaxis)</th>
                        <th>Oral Surgery</th>
                        <th>Restorative</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <label><input type="checkbox" name="services[]" value="Diagnosis: Consultation" class="services"> Consultation</label><br><hr>
                            <label><input type="checkbox" name="services[]" value="+ Medical Certificate" class="services"> w/ Medical Certificate</label>
                        </td>
                        <td>
                            <label><input type="checkbox" name="services[]" value="Periodontics (Oral Prohylaxis): Light-Moderate" class="services"> Light-Moderate</label><br>
                            <label><input type="checkbox" name="services[]" value="Periodontics (Oral Prohylaxis): Heavy" class="services"> Heavy</label><br><hr>
                            <label><input type="checkbox" name="services[]" value="+ Fluoride Treatment" class="services"> w/ Fluoride Treatment</label>
                        </td>
                        <td>
                            <label><input type="checkbox" name="services[]" value="Oral Surgery: Simple Extraction" class="services"> Simple Extraction</label><br>
                            <label><input type="checkbox" name="services[]" value="Oral Surgery: Complicated Extraction" class="services"> Complicated Extraction</label><br>
                            <label><input type="checkbox" name="services[]" value="Oral Surgery: Odontectomy" class="services"> Odontectomy</label>
                        </td>
                        <td>
                            <label><input type="checkbox" name="services[]" value="Restorative: Temporary" class="services">Temporary</label><br>
                            <label><input type="checkbox" name="services[]" value="Restorative: Composite" class="services">Composite</label><br>
                            <label><input type="checkbox" name="services[]" value="Restorative: Additional Surface" class="services">Additional Surface</label><br>
                            <label><input type="checkbox" name="services[]" value="Restorative: Pit & Fissure Sealant" class="services">Pit & Fissure Sealant</label>
                        </td>
                    </tr>
                </tbody>
                <thead>
                    <tr>
                        <th>Repair</th>
                        <th>Prosthodontics</th>
                        <th>Orthodontics</th>
                        <th>Others</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <label><input type="checkbox" name="services[]" value="Repair: Crack" class="services"> Crack</label><br>
                            <label><input type="checkbox" name="services[]" value="Repair: Broken with Impression" class="services"> Broken with Impression</label><br>
                            <hr><div>Missing Pontic:</div><hr>
                            <label><input type="checkbox" name="services[]" value="Repair (Missing Pontic): Plastic" class="services"> Plastic</label><br>
                            <label><input type="checkbox" name="services[]" value="Repair (Missing Pontic): Porcelain" class="services"> Porcelain</label>
                        </td>
                        <td>
                            <hr>Jacket Crown Per Unit<hr>
                            <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Plastic" class="services"> Plastic</label><br>
                            <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Porcelain Simple Metal" class="services"> Porcelain Simple Metal</label><br>
                            <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Porcelain Tilite" class="services"> Porcelain Tilite</label><br>
                            <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): E-max" class="services"> E-max</label><br>
                            <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Zirconia" class="services"> Zirconia</label><br><hr>
                            <label><input type="checkbox" name="services[]" value="Prosthodontics: Re-cementation" class="services"> Re-cementation</label>
                        </td>
                        <td>
                            <label><input type="checkbox" name="services[]" value="Orthodintics: Conventional Metal Brackets" class="services"> Conventional Metal Brackets</label><br>
                            <label><input type="checkbox" name="services[]" value="Orthodintics: Ceramic Brackets" class="services"> Ceramic Brackets</label><br>
                            <label><input type="checkbox" name="services[]" value="Orthodintics: Self-Ligating Metal Brackets" class="services"> Self-Ligating Metal Brackets</label><br>
                            <label><input type="checkbox" name="services[]" value="Orthodintics: Functional Retainer" class="services"> Functional Retainer</label><br>
                            <label><input type="checkbox" name="services[]" value="Orthodintics: Retainer with Design" class="services"> Retainer with Design</label><br>
                            <label><input type="checkbox" name="services[]" value="Orthodintics: Ortho Kit" class="services"> Ortho Kit</label><br>
                            <label><input type="checkbox" name="services[]" value="Orthodintics: Ortho Wax" class="services"> Ortho Wax</label><br>
                        </td>
                        <td>
                            <label><input type="checkbox" name="services[]" value="Others: Teeth Whitening" class="services"> Teeth Whitening</label><br>
                            <label><input type="checkbox" name="services[]" value="Others: Reline" class="services"> Reline</label><br>
                            <label><input type="checkbox" name="services[]" value="Others: Rebase" class="services"> Rebase</label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Partial Denture per Arch (Upper or Lower)</h3>
            <table border="1" cellpadding="10">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Pontic Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="partial_denture[Stayplate_Plastic]" value="Stayplate Plastic" class="partial_denture"> Stayplate Plastic
                            </label>
                        </td>
                        <td>
                            <select name="partial_denture[Stayplate_Plastic_pontic_count]">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="partial_denture[Stayplate_Porcelain]" value="Stayplate Porcelain" class="partial_denture"> Stayplate Porcelain
                            </label>
                        </td>
                        <td>
                            <select name="partial_denture[Stayplate_Porcelain_pontic_count]">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="partial_denture[One_piece_Plastic]" value="One-piece Plastic" class="partial_denture"> One-piece Plastic
                            </label>
                        </td>
                        <td>
                            <select name="partial_denture[One_piece_Plastic_pontic_count]">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="partial_denture[One_piece_Porcelain]" value="One-piece Porcelain" class="partial_denture"> One-piece Porcelain
                            </label>
                        </td>
                        <td>
                            <select name="partial_denture[One_piece_Porcelain_pontic_count]">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label><input type="checkbox" name="partial_denture[Flexite]" value="Flexite" class="partial_denture"> Flexite</label>
                        </td>
                        <td>
                            <select name="partial_denture[Flexite_pontic_count]">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Full Denture per Arch (Upper AND/OR Lower)</h3>
            <table border="1" cellpadding="10">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Range</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="full_denture[Stayplate_Plastic]" value="Stayplate Plastic" class="full_denture"> Stayplate Plastic
                            </label>
                        </td>
                        <td>
                            <select name="full_denture[Stayplate_Plastic_range]">
                                <option value="Upper">Upper</option>
                                <option value="Lower">Lower</option>
                                <option value="Upper AND Lower">Upper AND Lower</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="full_denture[Stayplate_Porcelain]" value="Stayplate Porcelain" class="full_denture"> Stayplate Porcelain
                            </label>
                        </td>
                        <td>
                            <select name="full_denture[Stayplate_Porcelain_range]">
                                <option value="Upper">Upper</option>
                                <option value="Lower">Lower</option>
                                <option value="Upper AND Lower">Upper AND Lower</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="full_denture[Ivocap]" value="Ivocap" class="full_denture"> Ivocap
                            </label>
                        </td>
                        <td>
                            <select name="full_denture[Ivocap_range]">
                                <option value="Upper">Upper</option>
                                <option value="Lower">Lower</option>
                                <option value="Upper AND Lower">Upper AND Lower</option>
                            </select>
                        </td>
                    </tr>   
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="Full_denture[Thermosen]" value="Thermosen" class="full_denture"> Thermosen
                            </label>
                        </td>
                        <td>
                            <select name="full_denture[Thermosen_range]">
                                <option value="Upper">Upper</option>
                                <option value="Lower">Lower</option>
                                <option value="Upper AND Lower">Upper AND Lower</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="button-container">
                <button type="submit">Register</button>
                <button type="button" onclick="window.location.href='check_appointment.php'">Cancel</button>
            </div>
        </form>
        <script src="patient_registry.js"></script>
    </div>
</body>
</html>
