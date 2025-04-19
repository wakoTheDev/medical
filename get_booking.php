<?php
session_start();

// Check if user is logged in as doctor
if(!isset($_SESSION['userId']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'doctor') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Get booking ID from request
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doctorId = $_SESSION['userId'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

// Fetch booking data ensuring it belongs to the logged-in doctor
$stmt = $conn->prepare("SELECT v.*, p.patient_name, p.patient_phone 
                      FROM doctor_visits v 
                      WHERE v.id = ? AND v.doctor_id = ?");
$stmt->bind_param("ii", $id, $doctorId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or access denied']);
    exit;
}

$booking = $result->fetch_assoc();
echo json_encode(['success' => true, 'booking' => $booking]);
?>
