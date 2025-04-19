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
      width: 260px;
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
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>E-pharmacy</h2>
    <ul class="sidebar-menu">
      <li onclick="showSection('dashboard')">Dashboard</li>
      <li onclick="showSection('medicine')">Medicine</li>
      <li onclick="showSection('prescriptions')">Prescriptions</li>
    <a href="mtaa.html" style="decoration:none;">  <li>Remote serives</li> </a>
    <a href="homepage.html">  <li onclick="showSection('Log out')">Log Out</li> </a>
    
    </ul>
  </div>

  <div class="main">
    <div id="dashboard" class="section">
      <h1>Welcome to the E-pharmacy Dashboard</h1>
      <p>Select medicines and manage your order from the cart panel.</p>
    </div>

    <div id="medicine" class="section hidden">
      <h2>Available Medicines</h2>
      <div class="medicine-grid">
        <div class="medicine-card">
          <h4>Panadol</h4>
          <p>Price: KSh 200</p>
          <button class="btn" onclick="addToCart('Panadol', 200)">Add to Cart</button>
        </div>
        <div class="medicine-card">
          <h4>Amoxil</h4>
          <p>Price: KSh 300</p>
          <button class="btn" onclick="addToCart('Amoxil', 300)">Add to Cart</button>
        </div>
        <div class="medicine-card">
          <h4>Augmentin</h4>
          <p>Price: KSh 850</p>
          <button class="btn" onclick="addToCart('Augmentin', 850)">Add to Cart</button>
        </div>
        <div class="medicine-card">
          <h4>Metformin</h4>
          <p>Price: KSh 500</p>
          <button class="btn" onclick="addToCart('Metformin', 500)">Add to Cart</button>
        </div>
        <div class="medicine-card">
          <h4>Cetrizine</h4>
          <p>Price: KSh 150</p>
          <button class="btn" onclick="addToCart('Cetrizine', 150)">Add to Cart</button>
        </div>
        <div class="medicine-card">
          <h4>Ventolin Inhaler</h4>
          <p>Price: KSh 1200</p>
          <button class="btn" onclick="addToCart('Ventolin Inhaler', 1200)">Add to Cart</button>
        </div>
        <div class="medicine-card">
          <h4>Loratadine</h4>
          <p>Price: KSh 300</p>
          <button class="btn" onclick="addToCart('Loratadine', 300)">Add to Cart</button>
        </div>
      </div>
    </div>

    <div id="prescriptions" class="section hidden">
      <h2>Prescriptions</h2>
      <div class="medicine-card">
        <h4>Prescription A</h4>
        <p>Includes: Amoxicillin, Paracetamol</p>
        <button class="btn" onclick="addToCart('Prescription A', 1000)">Add to Cart</button>
      </div>
    </div>
  </div>

  <div class="cart">
    <h3>🛒 My Cart</h3>
    <ul id="cart-items"></ul>
    <p><strong>Total:</strong> KSh <span id="cart-total">0</span></p>
    <button class="btn" onclick="showCheckoutOptions()">Checkout</button>

    <div class="checkout-options" id="checkout-options">
      <label for="payment">Payment Method:</label>
      <select id="payment">
        <option value="Cash">Cash</option>
        <option value="Mobile Money">Mobile Money</option>
      </select>

      <label for="delivery">Delivery Option:</label>
      <select id="delivery">
        <option value="Pickup">Pickup</option>
        <option value="Delivery">Delivery</option>
      </select>

      <button class="btn" onclick="confirmOrder()">Confirm Order</button>
    </div>
  </div>

  <script>
    const cart = [];

    function showSection(id) {
      document.querySelectorAll('.section').forEach(sec => sec.classList.add('hidden'));
      document.getElementById(id).classList.remove('hidden');
    }

    function addToCart(name, price) {
      cart.push({ name, price });
      updateCart();
      alert(`${name} added to cart!`);
    }

    function updateCart() {
      const cartList = document.getElementById('cart-items');
      const totalSpan = document.getElementById('cart-total');
      cartList.innerHTML = '';
      let total = 0;

      cart.forEach(item => {
        const li = document.createElement('li');
        li.textContent = `${item.name} - KSh ${item.price}`;
        cartList.appendChild(li);
        total += item.price;
      });

      totalSpan.textContent = total;
    }

    function showCheckoutOptions() {
      if (cart.length === 0) {
        alert("Your cart is empty.");
        return;
      }
      document.getElementById('checkout-options').style.display = 'block';
    }

    function confirmOrder() {
      const payment = document.getElementById('payment').value;
      const delivery = document.getElementById('delivery').value;
      const total = document.getElementById('cart-total').textContent;

      alert(`✅ Order confirmed!\nTotal: KSh ${total}\nPayment: ${payment}\nDelivery Mode: ${delivery}`);

      // Reset cart
      cart.length = 0;
      updateCart();
      document.getElementById('checkout-options').style.display = 'none';
    }
  </script>

</body>
</html>
