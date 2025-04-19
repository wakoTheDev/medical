<?php require "assets/function.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Pharmacy</title>
    <?php require "assets/autoloader.php"; ?>
    <link rel="stylesheet" href="css/customStyle.css">
    <!-- Include jQuery if it's not already included -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body style="background: url('photo/login.jpg'); background-size: cover; background-position: center;">
    <div class="login-box">
        <div class="well well-sm center" style="width: 25%; margin: auto; padding:4px 11px; margin-top: 100px; text-align: center;">
            <h3 class="center">E-Pharmacy Login</h3>
        </div>

        <div class="well well-sm" style="width: 25%; margin:auto; padding: 22px; margin-top: 20px; z-index: 6">
            <p class="login-box-msg">Sign in to access inventory</p>
            <form action="" method="post">
                <div class="form-group has-feedback">
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                    <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                </div>
                <div class="form-group has-feedback">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                </div>

                <button type="submit" name="login" class="btn btn-primary btn-block btn-flat">Sign In</button>

                <!-- Back to Homepage Button -->
                <a href="homepage.html" class="btn btn-home btn-block btn-flat">
                    <i class="glyphicon glyphicon-home"></i> Back to Homepage
                </a>
                <div class="text-center" style="margin-top: 15px;">
                    <p>Don't have an account? <a href="register.php">Create one</a></p>
                </div>
            </form>

        </div>

        <!-- Error Messages Display -->
        <br>
        <div class="alert alert-danger" id="error" style="width: 25%; margin: auto; display: none;"></div>
    </div>
</body>
</html>

<?php
if (isset($_POST['login'])) {
    $user = $_POST['email'];
    $pass = $_POST['password'];

    // Database connection
    $con = new mysqli('localhost', 'root', 'paul12wako', 'medical');

    if ($con->connect_error) {
        die("Connection failed: " . $con->connect_error);
    }

    // Prepare the SQL query to prevent SQL injection
    // Add user_type column to retrieve the role from database
    $stmt = $con->prepare("SELECT id, name, email, password, user_type FROM users WHERE email = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();

        // Compare plaintext password
        if ($pass === $data['password']) {
            // Start a session
            session_start();
            $_SESSION['userId'] = $data['id'];
            $_SESSION['name'] = $data['name'];
            $_SESSION['email'] = $data['email'];
            $_SESSION['user_type'] = $data['user_type']; // Store user type in session

            // Redirect based on user_type from the database
            switch ($data['user_type']) {
                case 'admin':
                    header('Location: admin.html');
                    break;
                case 'doctor':
                    header('Location: doctor.php');
                    break;
                case 'delivery':
                    header('Location: delivery.html');
                    break;
                case 'customer':
                    header('Location: customer.html');
                    break;
                default:
                    // Fallback to customer if user_type is not specified
                    header('Location: customer.html');
                    break;
            }
            exit;
        } else {
            // Show error if password is incorrect
            echo "<script>
                $(document).ready(function(){
                    $('#error').slideDown().html('Invalid email or password. Please try again.').delay(3000).fadeOut();
                });
            </script>";
        }
    } else {
        // Show error if email does not exist
        echo "<script>
            $(document).ready(function(){
                $('#error').slideDown().html('User does not exist. Please try again.').delay(3000).fadeOut();
            });
        </script>";
    }

    // Close the prepared statement and connection
    $stmt->close();
    $con->close();
}
?>