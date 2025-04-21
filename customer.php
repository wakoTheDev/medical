<?php
// Start session to manage user data
session_start();

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "medical";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user data if logged in
$user_data = null;
if (isset($_SESSION['userId'])) {
    $user_id = $_SESSION['userId'];
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
}

// Function to assign delivery person (selects the available delivery person with fewest active deliveries)
function assignDeliveryPerson($conn) {
    $query = "SELECT dp.id, u.name, u.email, u.phone 
              FROM delivery_persons dp
              JOIN users u ON dp.user_id = u.id
              LEFT JOIN medicine_deliveries md ON dp.id = md.delivery_person_id AND md.status NOT IN ('completed', 'cancelled')
              WHERE dp.availability = 'available'
              GROUP BY dp.id
              ORDER BY COUNT(md.id) ASC
              LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        // No available delivery person, try to get any delivery person
        $query = "SELECT dp.id, u.name, u.email, u.phone 
                  FROM delivery_persons dp
                  JOIN users u ON dp.user_id = u.id
                  LIMIT 1";
        
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        } else {
            // Return null if no delivery person found
            return null;
        }
    }
}

// Function to send email
function sendEmail($to, $subject, $message) {
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: E-Pharmacy <noreply@e-pharmacy.com>" . "\r\n";
    
    // Send email
    return mail($to, $subject, $message, $headers);
}

// Process checkout if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    // Get order details
    $user_id = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
    $customer_name = isset($_POST['customer_name']) ? $conn->real_escape_string($_POST['customer_name']) : '';
    $customer_email = isset($_POST['customer_email']) ? $conn->real_escape_string($_POST['customer_email']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? $conn->real_escape_string($_POST['customer_phone']) : '';
    $customer_address = isset($_POST['customer_address']) ? $conn->real_escape_string($_POST['customer_address']) : '';
    $payment_method = isset($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : '';
    $delivery_option = isset($_POST['delivery_option']) ? $conn->real_escape_string($_POST['delivery_option']) : '';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    
    // Get cart items
    $cart_items = isset($_POST['cart_items']) ? json_decode($_POST['cart_items'], true) : [];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert order into database
        $order_query = "INSERT INTO orders (user_id, total_amount, shipping_address, order_status, payment_method, payment_status, order_date) 
                        VALUES (?, ?, ?, 'pending', ?, 'pending', NOW())";
        
        $stmt = $conn->prepare($order_query);
        $stmt->bind_param("idss", $user_id, $total_amount, $customer_address, $payment_method);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating order: " . $stmt->error);
        }
        
        $order_id = $stmt->insert_id;
        
        // Insert order items
        foreach ($cart_items as $item) {
            // Assuming the item has a product_id property, otherwise you'll need to fetch it from the products table
            $product_id = isset($item['id']) ? $item['id'] : 1; 
            $quantity = isset($item['quantity']) ? $item['quantity'] : 1; 
            $unit_price = $item['price'];
            $subtotal = $unit_price * $quantity;
            
            $item_query = "INSERT INTO order_details (order_id, product_id, quantity, unit_price, subtotal) 
                           VALUES (?, ?, ?, ?, ?)";
            
            $item_stmt = $conn->prepare($item_query);
            $item_stmt->bind_param("iiidd", $order_id, $product_id, $quantity, $unit_price, $subtotal);
            
            if (!$item_stmt->execute()) {
                throw new Exception("Error adding order item: " . $item_stmt->error);
            }
        }
        
        // If delivery option is selected, assign a delivery person
        if ($delivery_option == "Delivery") {
            // Assign delivery person
            $delivery_person = assignDeliveryPerson($conn);
            
            if (!$delivery_person) {
                throw new Exception("No delivery person available at the moment.");
            }
            
            // Insert into medicine_deliveries table
            $delivery_query = "INSERT INTO medicine_deliveries 
                              (customer_name, customer_phone, delivery_address, medicine_details, delivery_time, 
                               request_date, delivery_person_id, status, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW(), ?, 'pending', NOW(), NOW())";
            
            $medicine_details = '';
            foreach ($cart_items as $item) {
                $medicine_details .= $item['name'] . " - KSh " . $item['price'] . "\n";
            }
            
            $delivery_stmt = $conn->prepare($delivery_query);
            $delivery_stmt->bind_param("ssssi", $customer_name, $customer_phone, $customer_address, $medicine_details, $delivery_person['id']);
            
            if (!$delivery_stmt->execute()) {
                throw new Exception("Error creating delivery: " . $delivery_stmt->error);
            }
            
            $delivery_id = $delivery_stmt->insert_id;
            
            // Update the order with delivery person id
            $update_order = "UPDATE orders SET delivery_person_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_order);
            $update_stmt->bind_param("ii", $delivery_person['id'], $order_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Error updating order with delivery person: " . $update_stmt->error);
            }
            
            // Prepare item list for email
            $items_html = "<ul>";
            $total = 0;
            foreach ($cart_items as $item) {
                $items_html .= "<li>{$item['name']} - KSh {$item['price']}</li>";
                $total += $item['price'];
            }
            $items_html .= "</ul>";
            
            // Send email to customer
            $customer_subject = "E-Pharmacy: Your Order #$order_id Confirmation";
            $customer_message = "
            <html>
            <head>
                <title>Order Confirmation</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background-color: #1e272e; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background-color: #f0f2f5; padding: 10px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Thank You for Your Order</h1>
                    </div>
                    <div class='content'>
                        <p>Dear $customer_name,</p>
                        <p>We've received your order #$order_id and it's being processed.</p>
                        
                        <h3>Order Details:</h3>
                        $items_html
                        <p><strong>Total Amount:</strong> KSh $total</p>
                        <p><strong>Payment Method:</strong> $payment_method</p>
                        <p><strong>Delivery Method:</strong> $delivery_option</p>
                        
                        <h3>Delivery Details:</h3>
                        <p><strong>Delivery Person:</strong> {$delivery_person['name']}</p>
                        <p><strong>Contact:</strong> {$delivery_person['phone']}</p>
                        <p><strong>Delivery Address:</strong> $customer_address</p>
                        
                        <p>We will notify you when your order is on the way.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " E-Pharmacy. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            sendEmail($customer_email, $customer_subject, $customer_message);
            
            // Send notification to delivery person
            $delivery_subject = "E-Pharmacy: New Delivery Assignment #$delivery_id";
            $delivery_message = "
            <html>
            <head>
                <title>New Delivery Assignment</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background-color: #1e272e; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { background-color: #f0f2f5; padding: 10px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>New Delivery Assignment</h1>
                    </div>
                    <div class='content'>
                        <p>Dear {$delivery_person['name']},</p>
                        <p>You have been assigned a new delivery (#$delivery_id).</p>
                        
                        <h3>Order Details:</h3>
                        $items_html
                        <p><strong>Total Amount:</strong> KSh $total</p>
                        
                        <h3>Customer Details:</h3>
                        <p><strong>Name:</strong> $customer_name</p>
                        <p><strong>Phone:</strong> $customer_phone</p>
                        <p><strong>Address:</strong> $customer_address</p>
                        
                        <p>Please confirm receipt of this assignment and proceed with the delivery.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " E-Pharmacy. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            sendEmail($delivery_person['email'], $delivery_subject, $delivery_message);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set response for AJAX
        $response = [
            'status' => 'success',
            'message' => 'Order processed successfully',
            'order_id' => $order_id,
            'delivery_person' => isset($delivery_person) ? $delivery_person['name'] : null
        ];
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Error processing order
        $response = [
            'status' => 'error',
            'message' => 'Error processing order: ' . $e->getMessage()
        ];
        
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>E-pharmacy Dashboard</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      background-color: #f0f2f5;
    }

    .sidebar {
      width: 220px;
      background-color: #1e272e;
      color: white;
      height: 100vh;
      padding: 1rem;
      position: fixed;
    }

    .sidebar h2 {
      text-align: center;
      margin-bottom: 1rem;
    }

    .sidebar-menu {
      list-style-type: none;
      padding: 0;
    }

    .sidebar-menu li {
      padding: 12px 16px;
      cursor: pointer;
      border-radius: 5px;
    }

    .sidebar-menu li:hover {
      background-color: #485460;
    }

    .main {
      margin-left: 220px;
      margin-right: 280px;
      padding: 1rem 2rem;
      flex-grow: 1;
    }

    .cart {
      width: 280px;
      background: #fff;
      height: 100vh;
      padding: 1rem;
      position: fixed;
      right: 0;
      top: 0;
      overflow-y: auto;
      border-left: 1px solid #ccc;
    }

    .medicine-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
    }

    .medicine-card {
      background: white;
      padding: 1rem;
      border-radius: 8px;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .medicine-card h4 {
      margin: 0.2rem 0;
    }

    .btn {
      background: #2980b9;
      color: white;
      border: none;
      padding: 0.5rem;
      border-radius: 4px;
      cursor: pointer;
      margin-top: 0.5rem;
      transition: background 0.3s;
    }

    .btn:hover {
      background: #1f6390;
    }

    .cart h3 {
      margin-top: 0;
    }

    .cart ul {
      list-style: none;
      padding: 0;
    }

    .cart li {
      padding: 5px 0;
      border-bottom: 1px solid #eee;
      font-size: 0.95rem;
    }

    .checkout-options {
      display: none;
      margin-top: 1rem;
    }

    .checkout-options select,
    .checkout-options input,
    .checkout-options button {
      width: 100%;
      margin-top: 0.5rem;
      padding: 0.5rem;
    }

    .hidden {
      display: none;
    }

    h1, h2 {
      color: #2c3e50;
    }
    
    .order-success {
      background-color: #d4edda;
      color: #155724;
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 15px;
      display: none;
    }
    
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
      background-color: #fefefe;
      margin: 15% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 500px;
      border-radius: 8px;
    }
    
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
    }
    
    .close:hover,
    .close:focus {
      color: black;
      text-decoration: none;
      cursor: pointer;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>E-pharmacy</h2>
    <ul class="sidebar-menu">
      <li onclick="showSection('dashboard')">Dashboard</li>
      <li onclick="showSection('medicine')">Medicine</li>
      <li onclick="showSection('prescriptions')">Prescriptions</li>
      <a href="mtaa.html" style="text-decoration:none; color:white;"><li>Remote services</li></a>
      <a href="homepage.html" style="text-decoration:none; color:white;"><li>Log Out</li></a>
    </ul>
  </div>

  <div class="main">
    <div id="order-success" class="order-success"></div>
    
    <div id="dashboard" class="section">
      <h1>Welcome to the E-pharmacy Dashboard</h1>
      <p>Select medicines and manage your order from the cart panel.</p>
    </div>

    <div id="medicine" class="section hidden">
      <h2>Available Medicines</h2>
      <div class="medicine-grid">
        <?php
          $product_query = "SELECT id, name, price FROM products";
          $product_result = $conn->query($product_query);

          if ($product_result && $product_result->num_rows > 0) {
              while ($product = $product_result->fetch_assoc()) {
                  $id = $product['id'];
                  $name = htmlspecialchars($product['name'], ENT_QUOTES);
                  $price = number_format($product['price'], 2);
                  echo "
                  <div class='medicine-card'>
                    <h4>{$name}</h4>
                    <p>Price: KSh {$price}</p>
                    <button class='btn' onclick=\"addToCart({$id}, '{$name}', {$product['price']})\">Add to Cart</button>
                  </div>
                  ";
              }
          } else {
              echo "<p>No medicines available.</p>";
          }
        ?>
    </div>
  </div>

  <div class="cart">
    <h3>🛒 My Cart</h3>
    <ul id="cart-items"></ul>
    <p><strong>Total:</strong> KSh <span id="cart-total">0</span></p>
    <button class="btn" onclick="showCheckoutOptions()">Checkout</button>

    <div class="checkout-options" id="checkout-options">
      <form id="checkout-form">
        <h4>Customer Information</h4>
        <input type="text" id="customer-name" name="customer_name" placeholder="Full Name" required>
        <input type="email" id="customer-email" name="customer_email" placeholder="Email Address" required>
        <input type="tel" id="customer-phone" name="customer_phone" placeholder="Phone Number" required>
        <input type="text" id="customer-address" name="customer_address" placeholder="Delivery Address" required>
        
        <h4>Payment Method</h4>
        <select id="payment" name="payment_method">
          <option value="Cash">Cash on Delivery</option>
          <option value="Mobile Money">Mobile Money</option>
          <option value="Credit Card">Credit Card</option>
        </select>

        <h4>Delivery Option</h4>
        <select id="delivery" name="delivery_option">
          <option value="Pickup">Pickup</option>
          <option value="Delivery">Home Delivery</option>
        </select>

        <button type="button" class="btn" onclick="processOrder()">Confirm Order</button>
      </form>
    </div>
  </div>
  
  <!-- Modal for order confirmation -->
  <div id="confirmation-modal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Order Confirmation</h3>
      <p id="modal-message"></p>
      <p id="modal-details"></p>
      <button class="btn" onclick="closeModal()">OK</button>
    </div>
  </div>

  <script>
    const cart = [];
    // Store user data from PHP to JavaScript
    const userData = <?php echo $user_data ? json_encode($user_data) : 'null'; ?>;

    function showSection(id) {
      document.querySelectorAll('.section').forEach(sec => sec.classList.add('hidden'));
      document.getElementById(id).classList.remove('hidden');
    }

    function addToCart(id, name, price) {
      cart.push({ id, name, price, quantity: 1 });
      updateCart();
      alert(`${name} added to cart!`);
    }

    function updateCart() {
      const cartList = document.getElementById('cart-items');
      const totalSpan = document.getElementById('cart-total');
      cartList.innerHTML = '';
      let total = 0;

      cart.forEach((item, index) => {
        const li = document.createElement('li');
        li.textContent = `${item.name} - KSh ${item.price}`;
        
        // Add remove button
        const removeBtn = document.createElement('span');
        removeBtn.textContent = ' ❌';
        removeBtn.style.cursor = 'pointer';
        removeBtn.onclick = function() { removeFromCart(index); };
        li.appendChild(removeBtn);
        
        cartList.appendChild(li);
        total += item.price;
      });

      totalSpan.textContent = total;
    }
    
    function removeFromCart(index) {
      cart.splice(index, 1);
      updateCart();
    }

    function showCheckoutOptions() {
      if (cart.length === 0) {
        alert("Your cart is empty.");
        return;
      }
      
      // Show checkout form
      document.getElementById('checkout-options').style.display = 'block';
      
      // Pre-fill customer information if user data is available
      if (userData) {
        document.getElementById('customer-name').value = userData.name || '';
        document.getElementById('customer-email').value = userData.email || '';
        document.getElementById('customer-phone').value = userData.phone || '';
        document.getElementById('customer-address').value = userData.address || '';
      }
    }
    
    function processOrder() {
      if (cart.length === 0) {
        alert("Your cart is empty.");
        return;
      }
      
      // Validate form fields
      const form = document.getElementById('checkout-form');
      const customerName = document.getElementById('customer-name').value;
      const customerEmail = document.getElementById('customer-email').value;
      const customerPhone = document.getElementById('customer-phone').value;
      const customerAddress = document.getElementById('customer-address').value;
      const paymentMethod = document.getElementById('payment').value;
      const deliveryOption = document.getElementById('delivery').value;
      
      if (!customerName || !customerEmail || !customerPhone || !customerAddress) {
        alert("Please fill in all required fields.");
        return;
      }
      
      // Calculate total
      const total = cart.reduce((sum, item) => sum + item.price, 0);
      
      // Prepare data for AJAX request
      const formData = new FormData();
      formData.append('checkout', 'true');
      formData.append('customer_name', customerName);
      formData.append('customer_email', customerEmail);
      formData.append('customer_phone', customerPhone);
      formData.append('customer_address', customerAddress);
      formData.append('payment_method', paymentMethod);
      formData.append('delivery_option', deliveryOption);
      formData.append('total_amount', total);
      formData.append('cart_items', JSON.stringify(cart));
      
      // Show loading state
      const submitBtn = document.querySelector('#checkout-form .btn');
      const originalBtnText = submitBtn.textContent;
      submitBtn.textContent = 'Processing...';
      submitBtn.disabled = true;
      
      // Send AJAX request
      const xhr = new XMLHttpRequest();
      xhr.open('POST', window.location.href, true);
      xhr.onload = function() {
        // Reset button state
        submitBtn.textContent = originalBtnText;
        submitBtn.disabled = false;
        
        if (xhr.status === 200) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.status === 'success') {
              // Show success message
              const modalMessage = document.getElementById('modal-message');
              const modalDetails = document.getElementById('modal-details');
              
              modalMessage.innerHTML = `Your order #${response.order_id} has been successfully placed!`;
              
              if (deliveryOption === 'Delivery') {
                modalDetails.innerHTML = `
                  <p>Your items will be delivered by ${response.delivery_person}.</p>
                  <p>A confirmation email has been sent to ${customerEmail} with all details.</p>
                `;
              } else {
                modalDetails.innerHTML = `
                  <p>You can pick up your order at our pharmacy.</p>
                  <p>A confirmation email has been sent to ${customerEmail} with all details.</p>
                `;
              }
              
              // Show modal
              document.getElementById('confirmation-modal').style.display = 'block';
              
              // Reset cart
              cart.length = 0;
              updateCart();
              document.getElementById('checkout-options').style.display = 'none';
              document.getElementById('checkout-form').reset();
            } else {
              alert("Error: " + response.message);
            }
          } catch (e) {
            console.error(e);
            alert("An error occurred while processing your order. Please try again.");
          }
        } else {
          alert("Server error. Please try again later.");
        }
      };
      
      xhr.onerror = function() {
        submitBtn.textContent = originalBtnText;
        submitBtn.disabled = false;
        alert("Network error. Please check your connection and try again.");
      };
      
      xhr.send(formData);
    }
    
    // Close modal when clicking the close button
    document.querySelector('.close').onclick = function() {
      closeModal();
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
      const modal = document.getElementById('confirmation-modal');
      if (event.target == modal) {
        closeModal();
      }
    }
    
    function closeModal() {
      document.getElementById('confirmation-modal').style.display = 'none';
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
      showSection('dashboard');
    });
  </script>

</body>
</html>