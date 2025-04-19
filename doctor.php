<?php
// Start session to access session variables
session_start();

// Check if user is logged in, if not redirect to login page
if(!isset($_SESSION['userId']) || !isset($_SESSION['name']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: login.php');
    exit;
}

// Get doctor ID and name from session
$doctorId = $_SESSION['userId'];
$doctorName = $_SESSION['name'];

// Include database connection
require_once 'config/db_connect.php';

// Function to get doctor stats
function getDoctorStats($conn, $doctorId) {
    $stats = array();
    
    // Get today's appointments count
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as today_appointments FROM doctor_visits 
                            WHERE doctor_id = ? AND DATE(appointment_time) = ? 
                            AND (status = 'pending' OR status = 'confirmed')");
    $stmt->bind_param("is", $doctorId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_appointments'] = $result->fetch_assoc()['today_appointments'];
    
    // Get pending requests count
    $stmt = $conn->prepare("SELECT COUNT(*) as pending_requests FROM doctor_visits 
                           WHERE doctor_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pending_requests'] = $result->fetch_assoc()['pending_requests'];
    
    // Get completed visits count
    $stmt = $conn->prepare("SELECT COUNT(*) as completed_visits FROM doctor_visits 
                           WHERE doctor_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['completed_visits'] = $result->fetch_assoc()['completed_visits'];
    
    // Get average rating - first check if feedback_rating column exists in doctor_visits table
    $stmt = $conn->prepare("SELECT AVG(feedback_rating) as avg_rating FROM doctor_visits 
                           WHERE doctor_id = ? AND feedback_rating IS NOT NULL");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $avgRating = $result->fetch_assoc()['avg_rating'];
    $stats['avg_rating'] = $avgRating ? number_format($avgRating, 1) : '0.0';
    
    return $stats;
}
// Get doctor stats
$stats = getDoctorStats($conn, $doctorId);

// Get bookings with filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare base query
$query = "SELECT dv.*
          FROM doctor_visits dv
          WHERE dv.doctor_id = ?";

// Add filters
$params = [$doctorId];
$types = "i";

if ($status_filter != 'all') {
    $query .= " AND dv.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_filter)) {
    $query .= " AND DATE(dv.appointment_time) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    $query .= " AND (dv.patient_name LIKE ? OR dv.patient_phone LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Add pagination
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get total count for pagination
$count_query = str_replace("SELECT dv.*", "SELECT COUNT(*) as total", $query);
$stmt = $conn->prepare($count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_result = $stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $items_per_page);

// Add order by and limit
$query .= " ORDER BY dv.booking_date DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = [];
while ($row = $bookings_result->fetch_assoc()) {
    $bookings[] = $row;
}

// Function to format date
function formatAppointmentDate($datetime) {
    $appointment_date = new DateTime($datetime);
    $today = new DateTime('today');
    $tomorrow = new DateTime('tomorrow');
    
    if ($appointment_date->format('Y-m-d') == $today->format('Y-m-d')) {
        return 'Today, ' . $appointment_date->format('g:i A');
    } elseif ($appointment_date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
        return 'Tomorrow, ' . $appointment_date->format('g:i A');
    } elseif ($appointment_date < $today) {
        // For past dates
        return $appointment_date->format('M j, Y, g:i A');
    } else {
        return $appointment_date->format('M j, Y, g:i A');
    }
}

// Function to get patient initials
function getInitials($fullName) {
    $parts = explode(' ', trim($fullName));
    $initials = '';

    foreach ($parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper($part[0]);
        }
    }

    return $initials;
}

// Get doctor initials for avatar
$initials = '';
$nameParts = explode(' ', $doctorName);
if(count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $initials = strtoupper(substr($doctorName, 0, 2));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mtaani Doctor - Doctor's Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS styles remain the same as in the original file */
        :root {
            --primary: #2A5C82;
            --secondary: #5BA4E6;
            --accent: #FF6B6B;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --text: #2C3E50;
            --background: #f8f9fa;
            --gray: #6c757d;
            --light: #f8f9fa;
            --dark: #343a40;
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: var(--primary);
            color: white;
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            margin: 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .logout-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }

        .logout-btn:hover {
            background-color: #ff5252;
        }

        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .stat-card h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .stat-card p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Filter Controls */
        .filter-controls {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-controls select, .filter-controls input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .filter-label {
            font-weight: 500;
            margin-right: 5px;
            color: var(--gray);
        }

        .search-box {
            flex-grow: 1;
            display: flex;
            align-items: center;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding-left: 35px;
        }

        .search-icon {
            position: absolute;
            left: 10px;
            color: var(--gray);
        }

        /* Bookings Table */
        .bookings-container {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th, 
        .bookings-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .bookings-table th {
            background-color: #f5f7fa;
            font-weight: 600;
            color: var(--primary);
        }

        .bookings-table tr:hover {
            background-color: #f9fafb;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-pending {
            background-color: #FFF3CD;
            color: #856404;
        }

        .badge-confirmed {
            background-color: #D1E7DD;
            color: #0f5132;
        }

        .badge-completed {
            background-color: #CFF4FC;
            color: #055160;
        }

        .badge-canceled {
            background-color: #F8D7DA;
            color: #842029;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-warning {
            background-color: var(--warning);
            color: var(--dark);
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-light {
            background-color: #e9ecef;
            color: var(--dark);
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .patient-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--primary);
            font-weight: 500;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: flex-end;
            gap: 5px;
            margin-top: 20px;
        }

        .page-item {
            display: inline-block;
            min-width: 30px;
            text-align: center;
            padding: 5px;
            border-radius: 4px;
            background: #e9ecef;
            color: var(--dark);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .page-item.active {
            background: var(--primary);
            color: white;
        }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            display: none;
        }

        .modal {
            background: white;
            border-radius: var(--radius);
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .modal-header {
            padding: 15px 20px;
            background: var(--primary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: none;
            z-index: 1001;
            border-left: 4px solid var(--success);
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-controls > div {
                width: 100%;
            }
            
            .bookings-table th:nth-child(3),
            .bookings-table td:nth-child(3),
            .bookings-table th:nth-child(4),
            .bookings-table td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>Doctor's Dashboard</h1>
            <div class="user-profile">
                <span>Dr. <?php echo htmlspecialchars($doctorName); ?></span>
                <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </header>

        <!-- Dashboard Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                <h3><?php echo $stats['today_appointments']; ?></h3>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <h3><?php echo $stats['pending_requests']; ?></h3>
                <p>Pending Requests</p>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <h3><?php echo $stats['completed_visits']; ?></h3>
                <p>Completed Visits</p>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-star"></i></div>
                <h3><?php echo $stats['avg_rating']; ?></h3>
                <p>Patient Rating</p>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="filter-controls">
            <div>
                <span class="filter-label">Status:</span>
                <select id="statusFilter" onchange="applyFilters()">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="canceled" <?php echo $status_filter == 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                </select>
            </div>
            <div>
                <span class="filter-label">Date:</span>
                <input type="date" id="dateFilter" value="<?php echo $date_filter; ?>" onchange="applyFilters()">
            </div>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search patients..." id="searchInput" value="<?php echo htmlspecialchars($search_query); ?>" oninput="delaySearch()">
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="bookings-container">
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Date & Time</th>
                        <th>Contact</th>
                        <th>Health Concerns</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bookingsTableBody">
                    <?php if (count($bookings) > 0): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr data-id="<?php echo $booking['id']; ?>">
                                <td>
                                    <div class="patient-info">
                                        <div class="patient-avatar"><?php echo getInitials($booking['patient_name']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo formatAppointmentDate($booking['booking_date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['patient_phone']); ?></td>
                                <td><?php echo htmlspecialchars($booking['patient_notes']); ?></td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    switch($booking['status']) {
                                        case 'pending':
                                            $status_class = 'badge-pending';
                                            break;
                                        case 'confirmed':
                                            $status_class = 'badge-confirmed';
                                            break;
                                        case 'completed':
                                            $status_class = 'badge-completed';
                                            break;
                                        case 'canceled':
                                            $status_class = 'badge-canceled';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($booking['status'] == 'pending'): ?>
                                            <button class="btn btn-primary" onclick="editBooking(<?php echo $booking['id']; ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-success" onclick="confirmBooking(<?php echo $booking['id']; ?>)"><i class="fas fa-check"></i></button>
                                            <button class="btn btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)"><i class="fas fa-times"></i></button>
                                        <?php elseif ($booking['status'] == 'confirmed'): ?>
                                            <button class="btn btn-primary" onclick="editBooking(<?php echo $booking['id']; ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-success" onclick="completeBooking(<?php echo $booking['id']; ?>)"><i class="fas fa-check-double"></i></button>
                                            <button class="btn btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)"><i class="fas fa-times"></i></button>
                                        <?php elseif ($booking['status'] == 'completed'): ?>
                                            <button class="btn btn-light" onclick="viewBookingDetails(<?php echo $booking['id']; ?>)"><i class="fas fa-eye"></i></button>
                                        <?php elseif ($booking['status'] == 'canceled'): ?>
                                            <button class="btn btn-light" onclick="viewBookingDetails(<?php echo $booking['id']; ?>)"><i class="fas fa-eye"></i></button>
                                            <button class="btn btn-primary" onclick="reopenBooking(<?php echo $booking['id']; ?>)"><i class="fas fa-redo"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No bookings found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="#" class="page-item" onclick="changePage(<?php echo $page - 1; ?>); return false;">Prev</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="#" class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>" 
                           onclick="changePage(<?php echo $i; ?>); return false;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="#" class="page-item" onclick="changePage(<?php echo $page + 1; ?>); return false;">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Booking Modal -->
    <div class="modal-backdrop" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Edit Booking Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editBookingId">
                <div class="form-group">
                    <label>Patient Name</label>
                    <input type="text" class="form-control" id="editPatientName" readonly>
                </div>
                <div class="form-group">
                    <label>Appointment Date & Time</label>
                    <input type="datetime-local" class="form-control" id="editAppointmentTime">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" id="editStatus">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="canceled">Canceled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Doctor Notes</label>
                    <textarea class="form-control" id="doctorNotes" placeholder="Add your notes here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveBookingChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification">Status updated successfully</div>

    <script>
        // Search delay timer
        let searchTimer;
        
        function delaySearch() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 500);
        }
        
        // Apply filters and reload page with query parameters
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const date = document.getElementById('dateFilter').value;
            const search = document.getElementById('searchInput').value;
            
            let queryParams = [];
            if (status !== 'all') queryParams.push(`status=${encodeURIComponent(status)}`);
            if (date) queryParams.push(`date=${encodeURIComponent(date)}`);
            if (search) queryParams.push(`search=${encodeURIComponent(search)}`);
            
            // Add current page if not first page
            const currentPage = <?php echo $page; ?>;
            if (currentPage > 1) queryParams.push(`page=${currentPage}`);
            
            const queryString = queryParams.length > 0 ? `?${queryParams.join('&')}` : '';
            window.location.href = `doctor_dashboard.php${queryString}`;
        }
        
        // Change page
        function changePage(page) {
            const status = document.getElementById('statusFilter').value;
            const date = document.getElementById('dateFilter').value;
            const search = document.getElementById('searchInput').value;
            
            let queryParams = [`page=${page}`];
            if (status !== 'all') queryParams.push(`status=${encodeURIComponent(status)}`);
            if (date) queryParams.push(`date=${encodeURIComponent(date)}`);
            if (search) queryParams.push(`search=${encodeURIComponent(search)}`);
            
            const queryString = queryParams.length > 0 ? `?${queryParams.join('&')}` : '';
            window.location.href = `doctor_dashboard.php${queryString}`;
        }
        
        
        function showModal() {
            document.getElementById('editModal').style.display = 'flex';
        }
        
        // Close Modal Function
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Show Notification
        function showNotification(message) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
        
        // Logout Function
        function logout() {
            // Send AJAX request to destroy session
            fetch('logout.php', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(response => {
                // Redirect to login page
                window.location.href = 'login.php';
            })
            .catch(error => {
                console.error('Logout failed:', error);
                // Fallback direct redirect if AJAX fails
                window.location.href = 'login.php';
            });
        }
        
        // Edit Booking
        function editBooking(id) {
            // Fetch booking details by ID using AJAX
            fetch(`get_booking.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const booking = data.booking;
                        document.getElementById('editBookingId').value = booking.id;
                        document.getElementById('editPatientName').value = booking.patient_name;
                        
                        // Format datetime for input
                        const dateTime = new Date(booking.appointment_datetime);
                        const formattedDateTime = dateTime.toISOString().slice(0, 16);
                        document.getElementById('editAppointmentTime').value = formattedDateTime;
                        
                        document.getElementById('editStatus').value = booking.status;
                        document.getElementById('doctorNotes').value = booking.doctor_notes || '';
                        showModal();
                    } else {
                        showNotification('Error loading booking details');
                    }
                })
                .catch(error => {
                    console.error('Error fetching booking details:', error);
                    showNotification('Error loading booking details');
                });
        }
        
        // Save Booking Changes
        function saveBookingChanges() {
            const bookingId = document.getElementById('editBookingId').value;
            const appointmentTime = document.getElementById('editAppointmentTime').value;
            const status = document.getElementById('editStatus').value;
            const notes = document.getElementById('doctorNotes').value;
            
            // Send AJAX request to update booking
            fetch('update_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${bookingId}&appointment_datetime=${encodeURIComponent(appointmentTime)}&status=${status}&doctor_notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    showNotification('Booking details updated successfully');
                    // Reload page to show updated data
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Error updating booking: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating booking:', error);
                showNotification('Error updating booking');
            });
        }
        
        // Confirm Booking
        function confirmBooking(id) {
            updateBookingStatus(id, 'confirmed', 'Booking confirmed successfully');
        }
        
        // Complete Booking
        function completeBooking(id) {
            updateBookingStatus(id, 'completed', 'Booking marked as completed');
        }
        
        // Cancel Booking
        function cancelBooking(id) {
            updateBookingStatus(id, 'canceled', 'Booking canceled');
        }
        
        // Reopen Booking
        function reopenBooking(id) {
            updateBookingStatus(id, 'pending', 'Booking reopened as pending');
        }
        
        // Update Booking Status
        function updateBookingStatus(id, status, successMessage) {
            // Send AJAX request to update status
            fetch('update_booking_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(successMessage);
                    // Reload page to show updated data
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Error updating status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
                showNotification('Error updating status');
            });
        }
        
        // View Booking Details
        function viewBookingDetails(id) {
            // Simply use the edit function but make fields readonly in the modal
            editBooking(id);
            // You could add additional code here to make form fields readonly if needed
        }
    </script>
</body>
</html>