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

// Collect appointment date and time from the form (assuming these are available from the form submission)
$appointmentDate = $_POST['appointmentDate'] ?? '';
$appointmentStartTime = $_POST['appointmentStartTime'] ?? '';
$appointmentEndTime = $_POST['appointmentEndTime'] ?? '';

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

// Fetch the appointment history for this patient, ordered by registry date
$appointment_sql = "SELECT * FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC";
$appointment_stmt = $conn->prepare($appointment_sql);
$appointment_stmt->bind_param("i", $id);
$appointment_stmt->execute();
$appointment_result = $appointment_stmt->get_result();
$appointment = $appointment_result->fetch_assoc();

$selected_services = explode(',', $appointment['services']);
$selected_services = array_map('trim', $selected_services);

// Close statements and connection
$stmt->close();
$stmtCheckDate->close();
$appointment_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="patient_registry.css">
    <title>Edit Record</title>
</head>
<body>
    <div class="container">
        <div class="header">
        <h1>Edit Record</h1>
            <div class ="bruh-button">
                <a href="check_appointment.php">View Appointments</a>
            </div>
        </div>
        <form action="update_record.php" method="POST">
            <div class="form-group">
                <label for="registryDate">Date of Registry:</label>
                <input type="date" id="registryDate" name="registryDate" value="<?php echo $patient['registry_date']; ?>" required>
            </div>

            <div class="name-section">
                <div class="form-group">
                    <label for="lastName">Last Name:</label>
                    <input type="text" id="lastName" name="lastName" value="<?php echo $patient['last_name']; ?>"required>
                </div>

                <div class="form-group">
                    <label for="givenName">Given Name:</label>
                    <input type="text" id="givenName" name="givenName" value="<?php echo $patient['first_name']; ?>"required>
                </div>

                <div class="form-group">
                    <label for="middleName">Middle Name:</label>
                    <input type="text" id="middleName" name="middleName" value="<?php echo $patient['middle_name']; ?>"required>
                </div>
            </div>

            <div class="form-group">
                <label for="birthday">Date of Birth:</label>
                <input type="date" id="birthday" name="birthday" value="<?php echo $patient['birthday']; ?>"required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $patient['email']; ?>">
            </div>

            <div class="form-group">
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" value="<?php echo $patient['address']; ?>">
            </div>

            <div class="form-group">
                <label for="mobileNumber">Mobile Number:</label>
                <input type="tel" id="mobileNumber" name="mobileNumber" value="<?php echo $patient['mobile_number']; ?>" placeholder="09*********" pattern="^[0-9]{11}$" required>
            </div>

            <div class="apt-section">
                <div class="form-group">
                    <label for="appointmentDate">Appointment Schedule:</label>
                    <input type="date" id="appointmentDate" name="appointmentDate" value="<?php echo $appointment['appointment_date']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="startTime">Start Time:</label>
                    <input type="time" id="appointmentStartTime" name="appointmentStartTime" value="<?php echo $appointment['appointment_start_time']; ?>" required min="13:00" max="17:00">
                    <p>Minimum is 1:00 PM</p>
                </div>
                <div class="form-group">
                    <label for="endTime">End Time:</label>
                    <input type="time" id="appointmentEndTime" name="appointmentEndTime" value="<?php echo $appointment['appointment_end_time']; ?>" required min="13:30" max="17:30">
                    <p>Maximum is 5:30 PM</p>
                </div>                
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
                                <label><input type="checkbox" name="services[]" value="Diagnosis: Consultation" <?php echo (in_array("Diagnosis: Consultation", $selected_services)) ? 'checked' : ''; ?>> Consultation</label><br>
                                <label><input type="checkbox" name="services[]" value="+ Medical Certificate" <?php echo (in_array("+ Medical Certificate", $selected_services)) ? 'checked' : ''; ?>> w/ Medical Certificate</label>
                            </td>
                            <td>
                                <label><input type="checkbox" name="services[]" value="Periodontics (Oral Prohylaxis): Light-Moderate" <?php echo (in_array("Periodontics (Oral Prohylaxis): Light-Moderate", $selected_services)) ? 'checked' : ''; ?>> Light-Moderate</label><br>
                                <label><input type="checkbox" name="services[]" value="Periodontics (Oral Prohylaxis): Heavy" <?php echo (in_array("Periodontics (Oral Prohylaxis): Heavy", $selected_services)) ? 'checked' : ''; ?>> Heavy</label><br>
                                <label><input type="checkbox" name="services[]" value="+ Fluoride Treatment" <?php echo (in_array("+ Fluoride Treatment", $selected_services)) ? 'checked' : ''; ?>> w/ Fluoride Treatment</label>
                            </td>
                            <td>
                                <label><input type="checkbox" name="services[]" value="Oral Surgery: Simple Extraction" <?php echo (in_array("Oral Surgery: Simple Extraction", $selected_services)) ? 'checked' : ''; ?>> Simple Extraction</label><br>
                                <label><input type="checkbox" name="services[]" value="Oral Surgery: Complicated Extraction" <?php echo (in_array("Oral Surgery: Complicated Extraction", $selected_services)) ? 'checked' : ''; ?>> Complicated Extraction</label><br>
                                <label><input type="checkbox" name="services[]" value="Oral Surgery: Odontectomy" <?php echo (in_array("Oral Surgery: Odontectomy", $selected_services)) ? 'checked' : ''; ?>> Odontectomy</label>
                            </td>
                            <td>
                                <label><input type="checkbox" name="services[]" value="Restorative: Temporary" <?php echo (in_array("Temporary", $selected_services)) ? 'checked' : ''; ?>> Temporary</label><br>
                                <label><input type="checkbox" name="services[]" value="Restorative: Composite" <?php echo (in_array("Composite", $selected_services)) ? 'checked' : ''; ?>> Composite</label><br>
                                <label><input type="checkbox" name="services[]" value="Restorative: Additional Surface" <?php echo (in_array("Additional Surface", $selected_services)) ? 'checked' : ''; ?>> Additional Surface</label><br>
                                <label><input type="checkbox" name="services[]" value="Restorative: Pit & Fissure Sealant" <?php echo (in_array("Pit & Fissure Sealant", $selected_services)) ? 'checked' : ''; ?>> Pit & Fissure Sealant</label>
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
                                <label><input type="checkbox" name="services[]" value="Repair: Crack" <?php echo (in_array("Repair: Crack", $selected_services)) ? 'checked' : ''; ?>> Crack</label><br>
                                <label><input type="checkbox" name="services[]" value="Repair: Broken with Impression" <?php echo (in_array("Repair: Broken with Impression", $selected_services)) ? 'checked' : ''; ?>> Broken with Impression</label><br>
                                <hr><div>Missing Pontic:</div><hr>
                                <label><input type="checkbox" name="services[]" value="Repair (Missing Pontic): Plastic" <?php echo (in_array("Repair (Missing Pontic): Plastic", $selected_services)) ? 'checked' : ''; ?>> Plastic</label><br>
                                <label><input type="checkbox" name="services[]" value="Repair (Missing Pontic): Porcelain" <?php echo (in_array("Repair (Missing Pontic): Porcelain", $selected_services)) ? 'checked' : ''; ?>> Porcelain</label>
                            </td>
                            <td>
                                <hr>Jacket Crown Per Unit<hr>
                                <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Plastic" <?php echo (in_array("Prosthodontics (Jacket Crown per Unit): Plastic", $selected_services)) ? 'checked' : ''; ?>> Plastic</label><br>
                                <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Porcelain Simple Metal" <?php echo (in_array("Prosthodontics (Jacket Crown per Unit): Porcelain Simple Metal", $selected_services)) ? 'checked' : ''; ?>> Porcelain Simple Metal</label><br>
                                <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Porcelain Tilite" <?php echo (in_array("Prosthodontics (Jacket Crown per Unit): Porcelain Tilite", $selected_services)) ? 'checked' : ''; ?>> Porcelain Tilite</label><br>
                                <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): E-max" <?php echo (in_array("Prosthodontics (Jacket Crown per Unit): E-max", $selected_services)) ? 'checked' : ''; ?>> E-max</label><br>
                                <label><input type="checkbox" name="services[]" value="Prosthodontics (Jacket Crown per Unit): Zirconia" <?php echo (in_array("Prosthodontics (Jacket Crown per Unit): Zirconia", $selected_services)) ? 'checked' : ''; ?>> Zirconia</label><br><hr>
                                <label><input type="checkbox" name="services[]" value="Prosthodontics: Re-cementation" <?php echo (in_array("Prosthodontics: Re-cementation", $selected_services)) ? 'checked' : ''; ?>> Re-cementation</label>
                            </td>
                            <td>
                                <label><input type="checkbox" name="services[]" value="Orthodontics: Conventional Metal Brackets" <?php echo (in_array("Orthodontics: Conventional Metal Brackets", $selected_services)) ? 'checked' : ''; ?>> Conventional Metal Brackets</label><br>
                                <label><input type="checkbox" name="services[]" value="Orthodontics: Ceramic Brackets" <?php echo (in_array("Orthodontics: Ceramic Brackets", $selected_services)) ? 'checked' : ''; ?>> Ceramic Brackets</label><br>
                                <label><input type="checkbox" name="services[]" value="Orthodontics: Self-Ligating Metal Brackets" <?php echo (in_array("Orthodontics: Self-Ligating Metal Brackets", $selected_services)) ? 'checked' : ''; ?>> Self-Ligating Metal Brackets</label><br>
                                <label><input type="checkbox" name="services[]" value="Orthodontics: Functional Retainer" <?php echo (in_array("Orthodontics: Functional Retainer", $selected_services)) ? 'checked' : ''; ?>> Functional Retainer</label><br>
                                <label><input type="checkbox" name="services[]" value="Orthodontics: Retainer with Design" <?php echo (in_array("Orthodontics: Retainer with Design", $selected_services)) ? 'checked' : ''; ?>> Retainer with Design</label><br>
                                <label><input type="checkbox" name="services[]" value="Orthodontics: Ortho Kit" <?php echo (in_array("Orthodontics: Ortho Kit", $selected_services)) ? 'checked' : ''; ?>> Ortho Kit</label><br>
                                <label><input type="checkbox" name="services[]" value="Orthodontics: Ortho Wax" <?php echo (in_array("Orthodontics: Ortho Wax", $selected_services)) ? 'checked' : ''; ?>> Ortho Wax</label><br>
                            </td>
                            <td>
                                <label><input type="checkbox" name="services[]" value="Others: Teeth Whitening" <?php echo (in_array("Others: Teeth Whitening", $selected_services)) ? 'checked' : ''; ?>> Teeth Whitening</label><br>
                                <label><input type="checkbox" name="services[]" value="Others: Reline" <?php echo (in_array("Others: Reline", $selected_services)) ? 'checked' : ''; ?>> Reline</label><br>
                                <label><input type="checkbox" name="services[]" value="Others: Rebase" <?php echo (in_array("Others: Rebase", $selected_services)) ? 'checked' : ''; ?>> Rebase</label>
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
                                    <label><input type="checkbox" name="partial_denture[Stayplate_Plastic]" value="Stayplate Plastic" <?php if (in_array('Stayplate Plastic', explode(', ', $appointment['partial_denture_service']))) echo 'checked'; ?>> Stayplate Plastic</label>
                                </td>
                                <td>
                                    <select name="partial_denture[Stayplate_Plastic_pontic_count]">
                                        <?php 
                                        for ($i = 1; $i <= 8; $i++) { 
                                            $selected = (in_array("Stayplate Plastic", explode(', ', $appointment['partial_denture_service'])) && in_array($i, explode(', ', $appointment['partial_denture_count']))) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $i; ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><input type="checkbox" name="partial_denture[Stayplate_Porcelain]" value="Stayplate Porcelain" <?php if (in_array('Stayplate Porcelain', explode(', ', $appointment['partial_denture_service']))) echo 'checked'; ?>> Stayplate Porcelain</label>
                                </td>
                                <td>
                                    <select name="partial_denture[Stayplate_Porcelain_pontic_count]">
                                        <?php 
                                        for ($i = 1; $i <= 8; $i++) { 
                                            $selected = (in_array("Stayplate Porcelain", explode(', ', $appointment['partial_denture_service'])) && in_array($i, explode(', ', $appointment['partial_denture_count']))) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $i; ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><input type="checkbox" name="partial_denture[One_piece_Plastic]" value="One-piece Plastic" <?php if (in_array('One-piece Plastic', explode(', ', $appointment['partial_denture_service']))) echo 'checked'; ?>> One-piece Plastic</label>
                                </td>
                                <td>
                                    <select name="partial_denture[One_piece_Plastic_pontic_count]">
                                        <?php 
                                        for ($i = 1; $i <= 8; $i++) { 
                                            $selected = (in_array("One-piece Plastic", explode(', ', $appointment['partial_denture_service'])) && in_array($i, explode(', ', $appointment['partial_denture_count']))) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $i; ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><input type="checkbox" name="partial_denture[One_piece_Porcelain]" value="One-piece Porcelain" <?php if (in_array('One-piece Porcelain', explode(', ', $appointment['partial_denture_service']))) echo 'checked'; ?>> One-piece Porcelain</label>
                                </td>
                                <td>
                                    <select name="partial_denture[One_piece_Porcelain_pontic_count]">
                                        <?php 
                                        for ($i = 1; $i <= 8; $i++) { 
                                            $selected = (in_array("One-piece Porcelain", explode(', ', $appointment['partial_denture_service'])) && in_array($i, explode(', ', $appointment['partial_denture_count']))) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $i; ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label><input type="checkbox" name="partial_denture[Flexite]" value="Flexite" <?php if (in_array('Flexite', explode(', ', $appointment['partial_denture_service']))) echo 'checked'; ?>> Flexite</label>
                                </td>
                                <td>
                                    <select name="partial_denture[Flexite_pontic_count]">
                                        <?php 
                                        for ($i = 1; $i <= 8; $i++) { 
                                            $selected = (in_array("Flexite", explode(', ', $appointment['partial_denture_service'])) && in_array($i, explode(', ', $appointment['partial_denture_count']))) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $i; ?></option>
                                        <?php } ?>
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
                                            <input type="checkbox" name="full_denture[Stayplate_Plastic]" value="Stayplate Plastic"
                                            <?php if (in_array("Stayplate Plastic", explode(', ', $appointment['full_denture_service']))) echo 'checked'; ?>>
                                            Stayplate Plastic
                                        </label>
                                    </td>
                                    <td>
                                        <select name="full_denture[Stayplate_Plastic_range]">
                                            <?php 
                                            $ranges = ["Upper", "Lower", "Upper AND Lower"];
                                            foreach ($ranges as $option) {
                                                $selected = (in_array("Stayplate Plastic", explode(', ', $appointment['full_denture_service'])) && in_array($option, explode(', ', $appointment['full_denture_range']))) ? 'selected' : '';
                                                echo "<option value='$option' $selected>$option</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="full_denture[Stayplate_Porcelain]" value="Stayplate Porcelain"
                                            <?php if (in_array("Stayplate Porcelain", explode(', ', $appointment['full_denture_service']))) echo 'checked'; ?>> Stayplate Porcelain
                                        </label>
                                    </td>
                                    <td>
                                        <select name="full_denture[Stayplate_Porcelain_range]">
                                            <?php 
                                            $ranges = ["Upper", "Lower", "Upper AND Lower"];
                                            foreach ($ranges as $option) {
                                                $selected = (in_array("Stayplate Porcelain", explode(', ', $appointment['full_denture_service'])) && in_array($option, explode(', ', $appointment['full_denture_range']))) ? 'selected' : '';
                                                echo "<option value='$option' $selected>$option</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="full_denture[Ivocap]" value="Ivocap"
                                            <?php if (in_array("Ivocap", explode(', ', $appointment['full_denture_service']))) echo 'checked'; ?>> Ivocap
                                        </label>
                                    </td>
                                    <td>
                                        <select name="full_denture[Ivocap_range]">
                                            <?php 
                                            $ranges = ["Upper", "Lower", "Upper AND Lower"];
                                            foreach ($ranges as $option) {
                                                $selected = (in_array("Ivocap", explode(', ', $appointment['full_denture_service'])) && in_array($option, explode(', ', $appointment['full_denture_range']))) ? 'selected' : '';
                                                echo "<option value='$option' $selected>$option</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>   
                                <tr>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="full_denture[Thermosen]" value="Thermosen"
                                            <?php if (in_array("Thermosen", explode(', ', $appointment['full_denture_service']))) echo 'checked'; ?>> Thermosen
                                        </label>
                                    </td>
                                    <td>
                                        <select name="full_denture[Thermosen_range]">
                                            <?php 
                                            $ranges = ["Upper", "Lower", "Upper AND Lower"];
                                            foreach ($ranges as $option) {
                                                $selected = (in_array("Thermosen", explode(', ', $appointment['full_denture_service'])) && in_array($option, explode(', ', $appointment['full_denture_range']))) ? 'selected' : '';
                                                echo "<option value='$option' $selected>$option</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

            <div class="form-group">
                <label for="addInfo">Additional Info:</label>
                <input type="text" name="addInfo" value="<?= $appointment['add_info']; ?>" required>
            </div>

            <div class="button-container">
                <button type="submit">Register</button>
            </div>
        </form>
        <script src="patient_registry.js"></script>
    </div>
</body>
</html>
