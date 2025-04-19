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

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$doctorId = $_SESSION['userId'];

// Validate data
if ($id <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status values
$validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// First, verify the booking belongs to this doctor
$stmt = $conn->prepare("SELECT id FROM doctor_visits WHERE id = ? AND doctor_id = ?");
$stmt->bind_param("ii", $id, $doctorId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or access denied']);
    exit;
}

// Update the booking status
$stmt = $conn->prepare("UPDATE doctor_visits SET status = ?, updated_at = NOW() WHERE id = ? AND doctor_id = ?");
$stmt->bind_param("sii", $status, $id, $doctorId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>