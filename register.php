<?php
require "assets/function.php";

if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $user_type = $_POST['user_type'];

    $con = new mysqli('localhost', 'root', 'paul12wako', 'medical');
    if ($con->connect_error) die("Connection failed: " . $con->connect_error);

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $check_email = $con->prepare("SELECT email FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $check_email->close();

            $stmt = $con->prepare("INSERT INTO users (name, email, password, phone, address, user_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $email, $password, $phone, $address, $user_type);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $stmt->close();

                // Role-specific insertions
                if ($user_type === 'doctor') {
                    $specialization = $_POST['specialization'] ?? null;
                    $license = $_POST['license_number'] ?? null;
                    $doc_stmt = $con->prepare("INSERT INTO doctors (user_id, specialization, license_number, approved) VALUES (?, ?, ?, 0)");
                    $doc_stmt->bind_param("iss", $user_id, $specialization, $license);
                    $doc_stmt->execute();
                    $doc_stmt->close();

                    // Redirect to "awaiting approval" page
                    header("Location: awaiting_approval.php?role=doctor");
                    exit();
                } 
                else if ($user_type === 'patient') {
                    $dob = $_POST['date_of_birth'] ?? null;
                    $history = $_POST['medical_history'] ?? null;
                    $patient_stmt = $con->prepare("INSERT INTO patients (user_id, date_of_birth, medical_history) VALUES (?, ?, ?)");
                    $patient_stmt->bind_param("iss", $user_id, $dob, $history);
                    $patient_stmt->execute();
                    $patient_stmt->close();

                    // Redirect to patient dashboard
                    header("Location: customer.php");
                    exit();
                } 
                else if ($user_type === 'delivery') {
                    $vehicle_type = $_POST['vehicle_type'] ?? null;
                    $vehicle_number = $_POST['vehicle_number'] ?? null;
                    $delivery_stmt = $con->prepare("INSERT INTO delivery_persons (user_id, vehicle_type, vehicle_number, approved) VALUES (?, ?, ?, 0)");
                    $delivery_stmt->bind_param("iss", $user_id, $vehicle_type, $vehicle_number);
                    $delivery_stmt->execute();
                    $delivery_stmt->close();

                    // Redirect to approval waiting page
                    header("Location: awaiting_approval.php?role=delivery");
                    exit();
                } 
                else if ($user_type === 'customer') {
                    // Redirect immediately
                    header("Location: customer.php");
                    exit();
                }

            } else {
                $error = "Registration failed. Try again.";
            }
        }
    }

    $con->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - E-Pharmacy</title>
    <?php require "assets/autoloader.php"; ?>
    <link rel="stylesheet" href="css/customStyle.css">
    <!-- Include jQuery if it's not already included -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body style="background: url('photo/login.jpg'); background-size: cover; background-position: center;">
    <div class="registration-box">
        <div class="well well-sm center" style="width: 35%; margin: auto; padding:4px 11px; margin-top: 50px; text-align: center;">
            <h3 class="center">E-Pharmacy Registration</h3>
        </div>

        <div class="well well-sm" style="width: 35%; margin:auto; padding: 22px; margin-top: 20px; z-index: 6">
            <p class="registration-box-msg">Create a new account</p>
            <form action="" method="post">
                <div class="form-group has-feedback">
                    <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                    <span class="glyphicon glyphicon-user form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                    <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                    <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input type="text" name="phone" class="form-control" placeholder="Phone Number" required>
                    <span class="glyphicon glyphicon-phone form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <textarea name="address" class="form-control" placeholder="Address" rows="2" required></textarea>
                </div>
                <div class="form-group">
                    <label for="user_type">Register as:</label>
                    <select name="user_type" class="form-control" required>
                        <option value="customer" selected>Customer</option>
                        <option value="doctor">Doctor</option>
                        <option value="patient">Patient</option>
                        <option value="delivery">Delivery Person</option>
                    </select>
                </div>

                <!-- Doctor-specific fields that appear when Doctor is selected -->
                <div id="doctor_fields" style="display: none;">
                    <div class="form-group has-feedback">
                        <input type="text" name="specialization" class="form-control" placeholder="Medical Specialization">
                    </div>
                    <div class="form-group has-feedback">
                        <input type="text" name="license_number" class="form-control" placeholder="License Number">
                    </div>
                </div>

                <!-- Patient-specific fields that appear when Patient is selected -->
                <div id="patient_fields" style="display: none;">
                    <div class="form-group has-feedback">
                        <input type="date" name="date_of_birth" class="form-control" placeholder="Date of Birth">
                    </div>
                    <div class="form-group has-feedback">
                        <textarea name="medical_history" class="form-control" placeholder="Brief Medical History" rows="2"></textarea>
                    </div>
                </div>

                <!-- Delivery Person-specific fields that appear when Delivery Person is selected -->
                <div id="delivery_fields" style="display: none;">
                    <div class="form-group has-feedback">
                        <input type="text" name="vehicle_type" class="form-control" placeholder="Vehicle Type">
                    </div>
                    <div class="form-group has-feedback">
                        <input type="text" name="vehicle_number" class="form-control" placeholder="Vehicle Number">
                    </div>
                </div>

                <div class="row">
                    <div class="col-xs-12">
                        <button type="submit" name="register" class="btn btn-primary btn-block btn-flat">Register</button>
                    </div>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" style="width: 35%; margin: auto;"><?php echo $error; ?></div>
                <?php endif; ?>
            </form>
            
            <div class="text-center" style="margin-top: 15px;">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
                
                <!-- Back to Homepage Button -->
                <a href="homepage.html" class="btn btn-home btn-block btn-flat">
                    <i class="glyphicon glyphicon-home"></i> Back to Homepage
                </a>
            </div>
        </div>

        <!-- Error/Success Messages Display -->
        <br>
        <div class="alert alert-danger" id="error" style="width: 35%; margin: auto; display: none;"></div>
        <div class="alert alert-success" id="success" style="width: 35%; margin: auto; display: none;"></div>
    </div>

    <script>
        $(document).ready(function() {
            // Show/hide role-specific fields based on selection
            $('select[name="user_type"]').change(function() {
                const selectedType = $(this).val();
                
                // Hide all role-specific fields first
                $('#doctor_fields, #patient_fields, #delivery_fields').hide();
                
                // Show fields based on selected role
                if (selectedType === 'doctor') {
                    $('#doctor_fields').show();
                } else if (selectedType === 'patient') {
                    $('#patient_fields').show();
                } else if (selectedType === 'delivery') {
                    $('#delivery_fields').show();
                }
            });
        });
    </script>
</body>
</html>

