<?php
session_start();

if (!isset($_SESSION['userId']) || $_SESSION['user_type'] !== 'delivery') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "medical");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get delivery_person_id for the logged-in user
$user_id = $_SESSION['userId'];
$person_sql = $conn->prepare("SELECT id FROM delivery_persons WHERE user_id = ?");
$person_sql->bind_param("i", $user_id);
$person_sql->execute();
$person_result = $person_sql->get_result();
$delivery_person_id = $person_result->fetch_assoc()['id'] ?? null;

// Update delivery status if posted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $delivery_id = $_POST['delivery_id'];
    $new_status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE medicine_deliveries SET status = ? WHERE id = ? AND delivery_person_id = ?");
    $stmt->bind_param("sii", $new_status, $delivery_id, $delivery_person_id);
    $stmt->execute();
    header("Location: delivery.php");
    exit;
}

// Fetch deliveries assigned to this delivery person
$deliveries = [];
if ($delivery_person_id) {
    $sql = "SELECT * FROM medicine_deliveries WHERE delivery_person_id = ? ORDER BY request_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delivery_person_id);
    $stmt->execute();
    $deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mtaani Doctor - Delivery Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            gap: 10px;
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

        /* Dashboard Stats */
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

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            position: relative;
        }

        .tab.active {
            color: var(--primary);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary);
        }

        /* Deliveries Container */
        .deliveries-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .delivery-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .delivery-header {
            padding: 15px;
            background: var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }

        .delivery-id {
            font-weight: 500;
            color: var(--primary);
        }

        .delivery-status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: #FFF3CD;
            color: #856404;
        }

        .status-in-progress {
            background-color: #D1ECF1;
            color: #0c5460;
        }

        .status-delivered {
            background-color: #D1E7DD;
            color: #0f5132;
        }

        .status-failed {
            background-color: #F8D7DA;
            color: #842029;
        }

        .delivery-body {
            padding: 15px;
        }

        .delivery-info {
            margin-bottom: 15px;
        }

        .delivery-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 3px;
        }

        .delivery-value {
            font-weight: 500;
        }

        .delivery-actions {
            display: flex;
            gap: 10px;
            padding: 15px;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            flex: 1;
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

        .btn-outline {
            background-color: transparent;
            border: 1px solid currentColor;
        }

        .btn-outline-primary {
            color: var(--primary);
        }

        /* Filter controls */
        .filter-bar {
            background: white;
            border-radius: var(--radius);
            padding: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--gray);
        }

        .filter-select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .search-box {
            flex-grow: 1;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 35px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
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

        /* Map container */
        .map-container {
            height: 400px;
            background: #e9ecef;
            border-radius: var(--radius);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .map-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--gray);
        }

        .map-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .deliveries-container {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group, .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>Delivery Dashboard</h1>
            <div class="user-profile">
                <span>David Omondi</span>
                <div class="user-avatar">DO</div>
            </div>
        </header>

        <!-- Dashboard Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-truck"></i></div>
                <h3>6</h3>
                <p>Today's Deliveries</p>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <h3>4</h3>
                <p>Pending Deliveries</p>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <h3>2</h3>
                <p>Completed Today</p>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-star"></i></div>
                <h3>4.9</h3>
                <p>Rating</p>
            </div>
        </div>

        <!-- Map View -->
        <div class="map-container">
            <div class="map-placeholder">
                <div class="map-icon"><i class="fas fa-map-marked-alt"></i></div>
                <p>Delivery Route Map</p>
                <small>Interactive map would be displayed here</small>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="pending">Pending Deliveries</div>
            <div class="tab" data-tab="progress">In Progress</div>
            <div class="tab" data-tab="completed">Completed</div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Sort by:</span>
                <select class="filter-select" id="sortFilter">
                    <option value="time">Delivery Time</option>
                    <option value="distance">Distance</option>
                    <option value="priority">Priority</option>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Date:</span>
                <input type="date" class="filter-select" id="dateFilter">
            </div>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Search deliveries..." id="searchInput">
            </div>
        </div>

        <!-- Deliveries Container -->
        <div class="deliveries-container" id="deliveriesContainer">
            <!-- Delivery Card 1 -->
            <?php foreach ($deliveries as $delivery): ?>
    <div class="delivery-card">
        <div class="delivery-header">
            <div class="delivery-id">#MD-<?= $delivery['id'] ?></div>
            <div class="delivery-status status-<?= str_replace(' ', '-', strtolower($delivery['status'])) ?>">
                <?= ucfirst($delivery['status']) ?>
            </div>
        </div>
        <div class="delivery-body">
            <div class="delivery-info">
                <div class="delivery-label">Customer Name</div>
                <div class="delivery-value"><?= htmlspecialchars($delivery['customer_name']) ?></div>
            </div>
            <div class="delivery-info">
                <div class="delivery-label">Delivery Address</div>
                <div class="delivery-value"><?= htmlspecialchars($delivery['delivery_address']) ?></div>
            </div>
            <div class="delivery-info">
                <div class="delivery-label">Phone Number</div>
                <div class="delivery-value"><?= htmlspecialchars($delivery['customer_phone']) ?></div>
            </div>
            <div class="delivery-info">
                <div class="delivery-label">Delivery Time</div>
                <div class="delivery-value"><?= date("D, H:i A", strtotime($delivery['delivery_time'])) ?></div>
            </div>
            <div class="delivery-info">
                <div class="delivery-label">Items</div>
                <div class="delivery-value"><?= nl2br(htmlspecialchars($delivery['medicine_details'])) ?></div>
            </div>
        </div>
        <div class="delivery-actions">
            <?php if ($delivery['status'] === 'pending'): ?>
                <form method="post">
                    <input type="hidden" name="delivery_id" value="<?= $delivery['id'] ?>">
                    <input type="hidden" name="status" value="in-progress">
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-play"></i> Start
                    </button>
                </form>
            <?php elseif ($delivery['status'] === 'in-progress'): ?>
                <form method="post">
                    <input type="hidden" name="delivery_id" value="<?= $delivery['id'] ?>">
                    <input type="hidden" name="status" value="delivered">
                    <button type="submit" name="update_status" class="btn btn-success">
                        <i class="fas fa-check"></i> Complete
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-outline btn-outline-primary" onclick="viewDeliveryDetails('MD-<?= $delivery['id'] ?>')">
                    <i class="fas fa-eye"></i> View
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
    </div>

    <!-- Update Status Modal -->
    <div class="modal-backdrop" id="statusModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Update Delivery Status</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Delivery ID</label>
                    <input type="text" class="form-control" id="deliveryId" readonly>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" id="deliveryStatus">
                        <option value="pending">Pending</option>
                        <option value="in-progress">In Progress</option>
                        <option value="delivered">Delivered</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea class="form-control" id="deliveryNotes" placeholder="Add delivery notes here..."></textarea>
                </div>
                <div class="form-group">
                    <label>Proof of Delivery</label>
                    <input type="file" class="form-control" id="deliveryProof">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="updateDeliveryStatus()">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Issue Report Modal -->
    <div class="modal-backdrop" id="issueModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Report Delivery Issue</h3>
                <button class="modal-close" onclick="closeIssueModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Delivery ID</label>
                    <input type="text" class="form-control" id="issueDeliveryId" readonly>
                </div>
                <div class="form-group">
                    <label>Issue Type</label>
                    <select class="form-control" id="issueType">
                        <option value="address">Address not found</option>
                        <option value="customer">Customer not available</option>
                        <option value="item">Item damaged/missing</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" id="issueDescription" placeholder="Describe the issue in detail..."></textarea>
                </div>
                <div class="form-group">
                    <label>Photo Evidence (Optional)</label>
                    <input type="file" class="form-control" id="issuePhoto">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" onclick="closeIssueModal()">Cancel</button>
                <button class="btn btn-warning" onclick="submitIssue()">Submit Issue</button>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification">Status updated successfully</div>

    <script>
        // Tab switching
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // In a real app, this would filter the deliveries based on the tab
                showNotification(`Switched to ${tab.textContent} tab`);
            });
        });
        
        // Show Status Modal
        function showStatusModal(deliveryId) {
            document.getElementById('deliveryId').value = deliveryId;
            document.getElementById('statusModal').style.display = 'flex';
        }
        
        // Close Status Modal
        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // Show Issue Modal
        function showIssueModal(deliveryId) {
            document.getElementById('issueDeliveryId').value = deliveryId;
            document.getElementById('issueModal').style.display = 'flex';
        }
        
        // Close Issue Modal
        function closeIssueModal() {
            document.getElementById('issueModal').style.display = 'none';
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
        
        // View Delivery Details
        function viewDeliveryDetails(deliveryId) {
            // In a real app, fetch delivery details by ID
            showStatusModal(deliveryId);
        }
        
        // Start Delivery
        function startDelivery(deliveryId) {
            // In a real app, update status in database
            showNotification(`Starting delivery for order ${deliveryId}`);
            
            // Update UI to reflect the change
            const deliveryCard = document.querySelector(`.delivery-card:has(.delivery-id:contains('${deliveryId}'))`);
            if(deliveryCard) {
                const statusSpan = deliveryCard.querySelector('.delivery-status');
                statusSpan.className = 'delivery-status status-in-progress';
                statusSpan.textContent = 'In Progress';
                
                const actionsDiv = deliveryCard.querySelector('.delivery-actions');
                actionsDiv.innerHTML = `
                    <button class="btn btn-warning" onclick="reportIssue('${deliveryId}')">
                        <i class="fas fa-exclamation-triangle"></i> Issue
                    </button>
                    <button class="btn btn-success" onclick="completeDelivery('${deliveryId}')">
                        <i class="fas fa-check"></i> Complete
                    </button>
                `;
            }
        }
        
        // Complete Delivery
        function completeDelivery(deliveryId) {
            showStatusModal(deliveryId);
            document.getElementById('deliveryStatus').value = 'delivered';
        }
        
        // Report Issue
        function reportIssue(deliveryId) {
            showIssueModal(deliveryId);
        }
        
        // Update Delivery Status
        function updateDeliveryStatus() {
            const deliveryId = document.getElementById('deliveryId').value;
            const status = document.getElementById('deliveryStatus').value;
            const notes = document.getElementById('deliveryNotes').value;
            
            // In a real app, update status in database
            closeModal();
            showNotification(`Delivery ${deliveryId} updated to ${status}`);
            
            // For demo, update UI
            if(status === 'delivered') {
                const deliveryCard = document.querySelector(`.delivery-card:has(.delivery-id:contains('${deliveryId}'))`);
                if(deliveryCard) {
                    const statusSpan = deliveryCard.querySelector('.delivery-status');
                    statusSpan.className = 'delivery-status status-delivered';
                    statusSpan.textContent = 'Delivered';
                    
                    const actionsDiv = deliveryCard.querySelector('.delivery-actions');
                    actionsDiv.innerHTML = `
                        <button class="btn btn-outline btn-outline-primary" onclick="viewDeliveryDetails('${deliveryId}')">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    `;
                }
            }
        }
        
        // Submit Issue
        function submitIssue() {
            const deliveryId = document.getElementById('issueDeliveryId').value;
            const issueType = document.getElementById('issueType').value;
            const description = document.getElementById('issueDescription').value;
            
            // In a real app, submit issue to database
            closeIssueModal();
            showNotification(`Issue reported for delivery ${deliveryId}`);
        }
        
        // Filter functionality
        document.getElementById('sortFilter').addEventListener('change', filterDeliveries);
        document.getElementById('dateFilter').addEventListener('change', filterDeliveries);
        document.getElementById('searchInput').addEventListener('input', filterDeliveries);
        
        function filterDeliveries() {

            const sort = document.getElementById('sortFilter').value;
            const date = document.getElementById('dateFilter').value;
            const search = document.getElementById('searchInput').value;
            
            showNotification(`Filtering: Sort=${sort}, Date=${date}, Search=${search}`);
        }
    </script>
</body>
</html>