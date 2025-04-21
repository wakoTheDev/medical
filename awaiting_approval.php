<?php
session_start();

// Optional: check if user is approved
// if ($_SESSION['approved'] == true) {
//     header("Location: dashboard.php");
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Approval Pending | Mtaani Doctor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * {
      box-sizing: border-box;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
    }

    body {
      height: 100vh;
      background: #f2f6fa;
      display: flex;
      justify-content: center;
      align-items: center;
      text-align: center;
    }

    .container {
      background: #ffffff;
      padding: 40px 30px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      max-width: 450px;
      width: 90%;
    }

    .container h1 {
      font-size: 1.8rem;
      color: #2A5C82;
      margin-bottom: 15px;
    }

    .container p {
      font-size: 1rem;
      color: #555;
      margin-bottom: 20px;
    }

    .spinner {
      width: 50px;
      height: 50px;
      border: 6px solid #e0e0e0;
      border-top: 6px solid #2A5C82;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 20px auto;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .note {
      font-size: 0.9rem;
      color: #888;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="spinner"></div>
    <h1>Account Under Review</h1>
    <p>Your account is currently awaiting approval from the admin team.</p>
    <p class="note">You'll be notified via email or SMS once approved.</p>
  </div>
</body>
</html>
