<?php
// booking_handler.php - Backend script for Mtaani Doctor Booking System

// Database configuration
$db_config = [
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'root',
    'password' => 'paul12wako',
    'database' => 'medical'
];

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Connect to database
function connectToDatabase($config) {
    try {
        $conn = new mysqli(
            $config['host'], 
            $config['username'], 
            $config['password'], 
            $config['database'], 
            $config['port']
        );
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        return $conn;
    } catch (Exception $e) {
        logError("Database connection error: " . $e->getMessage());
        return false;
    }
}

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Log errors to file
function logError($message) {
    $logFile = "error_log.txt";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Send email notification
function sendEmailNotification($recipient, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Mtaani Doctor <noreply@mtaanidoctor.com>" . "\r\n";
    
    try {
        return mail($recipient, $subject, $message, $headers);
    } catch (Exception $e) {
        logError("Email sending error: " . $e->getMessage());
        return false;
    }
}

// Handle doctor visit booking
function handleDoctorVisit($conn, $data) {
    // Sanitize data
    $patientName = sanitizeInput($data['patientName']);
    $patientPhone = sanitizeInput($data['patientPhone']);
    $appointmentTime = sanitizeInput($data['appointmentTime']);
    $patientNotes = sanitizeInput($data['patientNotes']);
    $bookingDate = date('Y-m-d H:i:s');
    $status = 'pending';
    
    // Create booking record
    $stmt = $conn->prepare("INSERT INTO doctor_visits (patient_name, patient_phone, appointment_time, patient_notes, booking_date, status) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        logError("Prepare failed: " . $conn->error);
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
    
    $stmt->bind_param("ssssss", $patientName, $patientPhone, $appointmentTime, $patientNotes, $bookingDate, $status);
    
    if (!$stmt->execute()) {
        logError("Execute failed: " . $stmt->error);
        return [
            'success' => false,
            'message' => 'Failed to save booking'
        ];
    }
    
    $bookingId = $conn->insert_id;
    $stmt->close();
    
    // Find available doctor and assign
    $doctor = assignDoctorToVisit($conn, $appointmentTime);
    
    if ($doctor) {
        // Update booking with assigned doctor
        $stmt = $conn->prepare("UPDATE doctor_visits SET doctor_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $doctor['id'], $bookingId);
        $stmt->execute();
        $stmt->close();
        
        // Send notification to doctor
        $emailSubject = "New Home Visit Booking #$bookingId";
        $emailMessage = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { padding: 20px; }
                    .header { background-color: #2A5C82; color: white; padding: 10px; }
                    .content { padding: 15px; }
                    .details { margin: 15px 0; }
                    .details table { width: 100%; }
                    .details td { padding: 5px; }
                    .footer { font-size: 12px; color: #888; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Home Visit Booking</h2>
                    </div>
                    <div class='content'>
                        <p>Dear Dr. {$doctor['name']},</p>
                        <p>You have been assigned a new home visit:</p>
                        
                        <div class='details'>
                            <table>
                                <tr>
                                    <td><strong>Booking ID:</strong></td>
                                    <td>$bookingId</td>
                                </tr>
                                <tr>
                                    <td><strong>Patient:</strong></td>
                                    <td>$patientName</td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td>$patientPhone</td>
                                </tr>
                                <tr>
                                    <td><strong>Appointment:</strong></td>
                                    <td>" . date('D, d M Y H:i', strtotime($appointmentTime)) . "</td>
                                </tr>
                                <tr>
                                    <td><strong>Notes:</strong></td>
                                    <td>$patientNotes</td>
                                </tr>
                            </table>
                        </div>
                        
                        <p>Please log in to your account to accept this assignment or contact support if you're unavailable.</p>
                        
                        <p>Thank you,<br>Mtaani Doctor Team</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        sendEmailNotification($doctor['email'], $emailSubject, $emailMessage);
        
        // Also send SMS notification (implementation would depend on SMS service)
        // sendSmsNotification($doctor['phone'], "New home visit booking #$bookingId on " . date('D, d M', strtotime($appointmentTime)) . " at " . date('H:i', strtotime($appointmentTime)));
        
        return [
            'success' => true,
            'message' => 'Booking confirmed! Dr. ' . $doctor['name'] . ' will arrive at ' . date('D, d M H:i', strtotime($appointmentTime)),
            'booking_id' => $bookingId
        ];
    } else {
        return [
            'success' => true,
            'message' => 'Booking received. We will assign a doctor shortly and send you confirmation.',
            'booking_id' => $bookingId
        ];
    }
}

// Assign doctor based on availability
function assignDoctorToVisit($conn, $appointmentTime) {
    // Get appointment date in correct format for comparison
    $appointmentDateTime = new DateTime($appointmentTime);
    $appointmentDate = $appointmentDateTime->format('Y-m-d');
    
    // Find doctors available at the requested time
    $stmt = $conn->prepare("
    SELECT d.id, u.name, u.email, u.phone
    FROM doctors d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN doctor_availability a ON d.id = a.doctor_id AND a.availability_date = ?
    LEFT JOIN doctor_visits v ON d.id = v.doctor_id AND DATE(v.appointment_time) = ?
    WHERE d.active = 1
    AND (a.available = 1 OR a.id IS NULL)
    GROUP BY d.id
    ORDER BY COUNT(v.id) ASC, RAND()
    LIMIT 1
    ");

    
    $stmt->bind_param("ss", $appointmentDate, $appointmentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    $stmt->close();
    
    // If no doctor is specifically available, get the one with least appointments
    $stmt = $conn->prepare("
        SELECT d.id, u.name, u.email, u.phone
        FROM doctors d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN doctor_visits v ON d.id = v.doctor_id AND DATE(v.appointment_time) = ?
        WHERE d.active = 1
        GROUP BY d.id
        ORDER BY COUNT(v.id) ASC, RAND()
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $appointmentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    $stmt->close();
    return null;
}

// Handle medicine delivery request
function handleMedicineDelivery($conn, $data) {
    // Sanitize data
    $customerName = sanitizeInput($data['customerName']);
    $customerPhone = sanitizeInput($data['customerPhone']);
    $deliveryAddress = sanitizeInput($data['deliveryAddress']);
    $medicineDetails = sanitizeInput($data['medicineDetails']);
    $deliveryTime = sanitizeInput($data['deliveryTime']);
    $requestDate = date('Y-m-d H:i:s');
    $status = 'pending';
    
    // Handle file upload if prescription image exists
    $prescriptionImagePath = null;
    if (isset($_FILES['prescriptionImage']) && $_FILES['prescriptionImage']['error'] == 0) {
        $uploadDir = 'uploads/prescriptions/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['prescriptionImage']['name']);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['prescriptionImage']['tmp_name'], $targetFile)) {
            $prescriptionImagePath = $targetFile;
        }
    }
    
    // Create delivery request record
    $stmt = $conn->prepare("INSERT INTO medicine_deliveries (customer_name, customer_phone, delivery_address, medicine_details, prescription_image, delivery_time, request_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        logError("Prepare failed: " . $conn->error);
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
    
    $stmt->bind_param("ssssssss", $customerName, $customerPhone, $deliveryAddress, $medicineDetails, $prescriptionImagePath, $deliveryTime, $requestDate, $status);
    
    if (!$stmt->execute()) {
        logError("Execute failed: " . $stmt->error);
        return [
            'success' => false,
            'message' => 'Failed to save delivery request'
        ];
    }
    
    $requestId = $conn->insert_id;
    $stmt->close();
    
    // Assign delivery person
    $deliveryPerson = assignDeliveryPerson($conn);
    
    if ($deliveryPerson) {
        // Update request with assigned delivery person
        $stmt = $conn->prepare("UPDATE medicine_deliveries SET delivery_person_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $deliveryPerson['id'], $requestId);
        $stmt->execute();
        $stmt->close();
        
        // Send notification to delivery person
        $emailSubject = "New Medicine Delivery Assignment #$requestId";
        $emailMessage = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { padding: 20px; }
                    .header { background-color: #2A5C82; color: white; padding: 10px; }
                    .content { padding: 15px; }
                    .details { margin: 15px 0; }
                    .details table { width: 100%; }
                    .details td { padding: 5px; }
                    .footer { font-size: 12px; color: #888; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Medicine Delivery Assignment</h2>
                    </div>
                    <div class='content'>
                        <p>Dear {$deliveryPerson['name']},</p>
                        <p>You have been assigned a new medicine delivery:</p>
                        
                        <div class='details'>
                            <table>
                                <tr>
                                    <td><strong>Order ID:</strong></td>
                                    <td>$requestId</td>
                                </tr>
                                <tr>
                                    <td><strong>Customer:</strong></td>
                                    <td>$customerName</td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td>$customerPhone</td>
                                </tr>
                                <tr>
                                    <td><strong>Address:</strong></td>
                                    <td>$deliveryAddress</td>
                                </tr>
                                <tr>
                                    <td><strong>Delivery Time:</strong></td>
                                    <td>" . date('D, d M Y H:i', strtotime($deliveryTime)) . "</td>
                                </tr>
                                <tr>
                                    <td><strong>Items:</strong></td>
                                    <td>$medicineDetails</td>
                                </tr>
                            </table>
                        </div>
                        
                        <p>Please log in to your account to view full details, including any prescription images.</p>
                        
                        <p>Thank you,<br>Mtaani Doctor Team</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        sendEmailNotification($deliveryPerson['email'], $emailSubject, $emailMessage);
        
        // Also send SMS notification (implementation would depend on SMS service)
        // sendSmsNotification($deliveryPerson['phone'], "New medicine delivery #$requestId to $deliveryAddress");
        
        return [
            'success' => true,
            'message' => 'Medicine delivery request confirmed! Your medicines will be delivered at ' . date('D, d M H:i', strtotime($deliveryTime)),
            'request_id' => $requestId
        ];
    } else {
        return [
            'success' => true,
            'message' => 'Delivery request received. A delivery person will be assigned shortly.',
            'request_id' => $requestId
        ];
    }
}

// Assign delivery person based on availability and workload
function assignDeliveryPerson($conn) {
    // Get current date for workload calculation
    $currentDate = date('Y-m-d');
    
    // Find delivery person with the least deliveries for today and who is available
    $stmt = $conn->prepare("
        SELECT dp.id, dp.name, dp.email, dp.phone
        FROM delivery_personnel dp
        LEFT JOIN medicine_deliveries md ON dp.id = md.delivery_person_id 
            AND DATE(md.delivery_time) = ? 
            AND md.status IN ('pending', 'in_progress')
        WHERE dp.active = 1
        GROUP BY dp.id
        ORDER BY COUNT(md.id) ASC, dp.last_assigned ASC, RAND()
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $deliveryPerson = $result->fetch_assoc();
        
        // Update last_assigned timestamp for this delivery person
        $updateStmt = $conn->prepare("UPDATE delivery_personnel SET last_assigned = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $deliveryPerson['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        return $deliveryPerson;
    }
    
    $stmt->close();
    return null;
}

// Main handler
try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        $response['message'] = 'Method not allowed';
        echo json_encode($response);
        exit;
    }
    
    // Connect to database
    $conn = connectToDatabase($db_config);
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Determine request type
    $requestType = isset($_POST['request_type']) ? $_POST['request_type'] : '';
    
    switch ($requestType) {
        case 'doctor_visit':
            $response = handleDoctorVisit($conn, $_POST);
            break;
        
        case 'medicine_delivery':
            $response = handleMedicineDelivery($conn, $_POST);
            break;
        
        default:
            $response['message'] = 'Invalid request type';
    }
    
    // Close database connection
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    logError($e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>