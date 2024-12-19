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

$appointment_id = $_GET['id'];

// Fetch appointment data from the database
$sql = "
    SELECT a.id AS appointment_id, 
           a.patient_id, 
           a.appointment_date, 
           a.appointment_start_time, 
           a.appointment_end_time, 
           a.services, 
           a.add_info, 
           a.partial_denture_service, 
           a.partial_denture_count, 
           a.full_denture_service, 
           a.full_denture_range, 
           a.patient_name
    FROM appointments a
    INNER JOIN patient_registry p ON a.patient_id = p.id
    WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $appointment_id); 
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

// Split services for easier manipulation
$selected_services = explode(',', $appointment['services']);
$selected_services = array_map('trim', $selected_services);

// Split partial denture data
$partial_denture_service = explode(',', $appointment['partial_denture_service']);
$partial_denture_count = explode(',', $appointment['partial_denture_count']);

// Store these values for later use
$selected_pontic_count = array();
foreach ($partial_denture_service as $key => $service) {
    $selected_pontic_count[] = $partial_denture_count[$key];
}

$patient_id = $appointment['patient_id']; // Get the patient ID associated with the appointment

$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointmentDate = $_POST['appointment_date'];
    $appointmentStartTime = $_POST['appointmentStartTime'];
    $appointmentEndTime = $_POST['appointmentEndTime'];
    $addInfo = $_POST['addInfo'];

    $services = isset($_POST['services']) ? implode(", ", $_POST['services']) : '';

    // Handle partial denture data (with pontic counts from select)
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

    // Handle full denture data (with range)
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
    $sqlCheckDate = "SELECT * FROM appointments WHERE appointment_date = ? AND id != ?";
    $stmtCheckDate = $conn->prepare($sqlCheckDate);
    $stmtCheckDate->bind_param("si", $appointmentDate, $appointment_id); // Add the current appointment ID
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
        echo "<script>alert('This appointment time overlaps with an existing appointment. Please choose another time.'); window.location.href = 'check_appointment.php';</script>";
        exit();
    }


    // Update the appointment in the database
    $update_sql = "
    UPDATE appointments 
    SET appointment_date = ?, 
        appointment_start_time = ?, 
        appointment_end_time = ?, 
        services = ?,
        partial_denture_service = ?,
        partial_denture_count = ?,
        full_denture_service = ?,
        full_denture_range = ?,
        add_info = ?
    WHERE id = ?";

    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("sssssssssi", $appointmentDate, $appointmentStartTime, $appointmentEndTime, $services, $partialDentureStr, $partialDentureCountStr, 
    $fullDentureStr, $fullDentureRangesStr, $addInfo, $appointment_id);

    if ($stmt_update->execute()) {
        // Redirect to patient record view, passing the patient's ID (retrieved from the appointment record)
        $stmt = $conn->prepare("SELECT patient_id FROM appointments WHERE id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $stmt->bind_result($patient_id);
        $stmt->fetch();
        echo 
        "<script>alert('Appointment updated successfully.');
        window.location.href='view_record.php?id=" . $patient_id . "';</script>";
    } else {
        echo "<script>alert('Error updating appointment.');</script>";
    }

    $stmt_update->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="patient_registry.css">
    <title>Edit Appointment</title>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Appointment</h1>
            <div class="bruh-button">
                <a href="check_appointment.php">Go back to Appointments</a>
            </div>
        </div>
        <form method="post">
            <div class="form-group">
                <label for="fullName">Full Name:</label>
                <input type="text" id="fullName" name="fullName" value="<?= $appointment['patient_name']; ?>" readonly>
            </div>

            <div class="apt-section">
                <div class="form-group">
                    <label for="appointmentDate">Appointment Date:</label>
                    <input type="date" name="appointment_date" value="<?= $appointment['appointment_date']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="appointmentStartTime">Start Time:</label>
                    <input type="time" name="appointmentStartTime" value="<?= $appointment['appointment_start_time']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="appointmentEndTime">End Time:</label>
                    <input type="time" name="appointmentEndTime" value="<?= $appointment['appointment_end_time']; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="addInfo">Additional Info:</label>
                <input type="text" name="addInfo" value="<?= $appointment['add_info']; ?>" required> <!-- Fixed name attribute -->
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

            <div class="button-container">
                <button type="submit">Update</button>
                <button type="button" onclick="window.location.href='check_appointment.php'">Cancel</button>
            </div>
        </form>
    </div>
</body>
</html>