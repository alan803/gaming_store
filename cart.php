<?php
session_start();
include 'connection.php';

// Ensure user is logged in
if (!isset($_SESSION['auth_user_id'])) {
    $_SESSION['login_redirect'] = 'cart.php';
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['auth_user_id'];

// Fetch cart items with product details and count quantities
$cart_items = [];
$subtotal = 0;
$cart_query = "
    SELECT c.cart_id, c.product_id, c.price, p.name, p.image_name, p.stock,
           COUNT(c.product_id) as quantity
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
    GROUP BY c.product_id, c.price, p.name, p.image_name, p.stock
";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $subtotal += $row['price'] * $row['quantity'];
    }
}
$stmt->close();

// Calculate cart count for badge (total number of items)
$cart_count_query = "SELECT COUNT(*) as total FROM cart WHERE user_id = ?";
$stmt = $conn->prepare($cart_count_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --dark: #111827;
            --light: #f9fafb;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
        }
        
        html, body {
            width: 100%;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #ffffff;
            color: #333333;
            position: relative;
            line-height: 1.7;
            font-size: 1.05rem;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .neon-text {
            text-shadow: 0 0 10px rgba(99, 102, 241, 0.2);
        }
        .cart-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .cart-item:hover {
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.15);
        }
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-light));
            color: white;
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(45deg, var(--primary-light), var(--primary));
            transition: width 0.3s ease;
            z-index: -1;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.3);
        }
        .btn-primary:hover::before {
            width: 100%;
        }
        .nav-link {
            position: relative;
            color: #4b5563;
            font-size: 1.05rem;
            font-weight: 500;
            padding: 0.5rem 0;
        }
        .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background: #4f46e5;
            transition: width 0.3s ease;
        }
        .nav-link:hover {
            color: #1f2937;
        }
        .nav-link:hover:after {
            width: 100%;
        }
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background-color: white !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
        nav .container {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .logo-icon {
            width: 2.5rem;
            height: 2.5rem;
            font-size: 1.25rem;
        }
        nav .nav-actions {
            gap: 1.5rem;
        }
        nav .nav-actions button,
        nav .nav-actions a {
            font-size: 1.15rem;
        }
        .cart-badge {
            width: 1.25rem;
            height: 1.25rem;
            font-size: 0.7rem;
            top: -0.5rem;
            right: -0.5rem;
        }
        section {
            padding: 6rem 0;
        }
        @media (max-width: 768px) {
            section {
                padding: 3rem 0;
            }
            nav .container {
                padding-top: 1rem;
                padding-bottom: 1rem;
            }
            .logo-text {
                font-size: 1.25rem;
            }
            .logo-icon {
                width: 2rem;
                height: 2rem;
                font-size: 1rem;
            }
        }
        h1, h2, h3, h4, h5, h6 {
            color: #111827;
        }
        section {
            background-color: #ffffff;
        }
        section.bg-gray-900 {
            background-color: #f9fafb !important;
        }
        footer {
            background-color: #f3f4f6 !important;
            border-top: 1px solid #e5e7eb;
        }
        input, select, textarea {
            background-color: #ffffff !important;
            border: 1px solid #d1d5db !important;
        }
        .text-yellow-400 {
            color: #f59e0b;
        }
        .text-gray-400 {
            color: #6b7280 !important;
        }
        .text-gray-400:hover {
            color: #4b5563 !important;
        }
        .bg-gray-800\/50 {
            background-color: #ffffff !important;
            border: 1px solid #e5e7eb !important;
        }
        .from-indigo-900\/90 {
            background: linear-gradient(to right, rgba(79, 70, 229, 0.9), rgba(99, 102, 241, 0.9)) !important;
        }
        .dropdown-menu {
            transition: all 0.2s ease;
            transform-origin: top right;
            min-width: 8rem;
            padding: 0.25rem 0;
            border-radius: 0.5rem;
        }
        .dropdown-menu.hidden {
            transform: scaleY(0);
            opacity: 0;
        }
        .dropdown-menu:not(.hidden) {
            transform: scaleY(1);
            opacity: 1;
        }
        .dropdown-menu a,
        .dropdown-menu button {
            font-size: 0.8125rem;
            padding: 0.375rem 0.75rem;
            white-space: nowrap;
        }
        .mobile-menu {
            transition: transform 0.3s ease;
        }
        .mobile-menu.hidden {
            transform: translateX(100%);
        }
        .mobile-menu:not(.hidden) {
            transform: translateX(0);
        }
        .container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .container {
                padding: 0 1.5rem;
            }
        }
        img {
            max-width: 100%;
            height: auto;
        }
        .quantity-select {
            width: 100px;
            padding: 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
            font-size: 0.875rem;
        }
        .remove-btn {
            transition: opacity 0.3s ease;
        }
        .remove-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white shadow-sm">
        <div class="container mx-auto px-4 py-5">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="logo-icon bg-indigo-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-gamepad text-white"></i>
                    </div>
                    <span class="logo-text bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">NexusGear</span>
                </div>
                <div class="hidden md:flex space-x-10">
                    <a href="homepage.php" class="nav-link text-gray-600 hover:text-indigo-600">Home</a>
                    <a href="homepage.php#about" class="nav-link text-gray-600 hover:text-indigo-600">About</a>
                    <a href="products.php" class="nav-link text-gray-600 hover:text-indigo-600">Products</a>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="cart.php" class="text-gray-600 hover:text-indigo-600 relative transition-colors">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span class="cart-badge absolute -top-2 -right-2 bg-indigo-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $cart_count; ?></span>
                    </a>
                    <?php if (isset($_SESSION['auth_user_id'])): ?>
                        <div class="relative group">
                            <button id="profileDropdownButton" class="flex items-center space-x-2 text-gray-700 hover:text-indigo-600 transition-colors" aria-expanded="false">
                                <span class="hidden md:inline font-medium"><?php echo htmlspecialchars($_SESSION['auth_username']); ?></span>
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                                    <i class="fas fa-user text-sm"></i>
                                </div>
                            </button>
                            <div id="profileDropdown" class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50 hidden border border-gray-100">
                                <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-user-circle mr-3 text-gray-400 w-5"></i>Profile
                                </a>
                                <a href="orders.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-shopping-bag mr-3 text-gray-400 w-5"></i>My Orders
                                </a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <form action="logout.php" method="post" class="w-full">
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center">
                                        <i class="fas fa-sign-out-alt mr-3 text-red-400 w-5"></i>Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center space-x-4">
                            <a href="login.php" class="text-gray-600 hover:text-indigo-600 transition-colors text-sm font-medium">Sign In</a>
                            <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Sign Up</a>
                        </div>
                    <?php endif; ?>
                    <button id="mobileMenuButton" aria-label="Toggle mobile menu" class="md:hidden text-gray-600 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            <!-- Mobile Menu -->
            <div id="mobileMenu" class="mobile-menu md:hidden fixed top-0 right-0 h-full w-72 bg-white shadow-2xl z-50 hidden overflow-y-auto">
                <div class="flex flex-col p-6">
                    <button id="closeMobileMenu" class="self-end text-gray-600 hover:text-indigo-600 mb-6 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                    <a href="homepage.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Home</a>
                    <a href="homepage.php#about" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">About</a>
                    <a href="products.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Products</a>
                    <a href="categories.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Categories</a>
                    <?php if (isset($_SESSION['auth_user_id'])): ?>
                        <div class="mt-6 pt-6 border-t-2 border-gray-200">
                            <p class="text-sm text-gray-500 mb-4">Account</p>
                            <a href="profile.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="orders.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium">
                                <i class="fas fa-shopping-bag mr-2"></i>My Orders
                            </a>
                            <form action="logout.php" method="post" class="w-full mt-2">
                                <button type="submit" class="w-full text-left py-3 text-red-600 hover:text-red-700 font-medium">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="mt-6 pt-6 border-t-2 border-gray-200 space-y-3">
                            <a href="login.php" class="block py-3 text-center text-gray-600 hover:text-indigo-600 font-medium border border-gray-300 rounded-lg">Login</a>
                            <a href="register.php" class="block py-3 text-center bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Cart Section -->
    <section class="py-20 gradient-bg cart-section">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold mb-8">Your Cart</h2>
            <?php if (empty($cart_items)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-shopping-cart text-5xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600 text-lg mb-6">Your cart is empty</p>
                    <a href="products.php" class="btn-primary inline-block">Shop Now</a>
                </div>
            <?php else: ?>
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Cart Items -->
                    <div class="lg:w-2/3">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item flex flex-col sm:flex-row items-start sm:items-center p-6 mb-4" data-product-id="<?php echo $item['product_id']; ?>">
                                <div class="w-24 h-24 sm:w-32 sm:h-32 flex-shrink-0">
                                    <img src="<?php echo htmlspecialchars('uploads/products/' . $item['image_name']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="w-full h-full object-cover rounded-lg">
                                </div>
                                <div class="flex-1 sm:ml-6 mt-4 sm:mt-0">
                                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Price: $<?php echo number_format($item['price'], 2); ?></p>
                                    <p class="text-sm text-gray-600">Stock: <?php echo $item['stock'] > 0 ? $item['stock'] . ' left' : 'Out of stock'; ?></p>
                                    <div class="mt-2">
                                        <label for="quantity-<?php echo $item['product_id']; ?>" class="text-sm text-gray-600">Quantity:</label>
                                        <select id="quantity-<?php echo $item['product_id']; ?>" 
                                                class="quantity-select ml-2" 
                                                onchange="updateQuantity(<?php echo $item['product_id']; ?>, this.value)">
                                            <?php for ($i = 1; $i <= min($item['stock'], 10); $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $i == $item['quantity'] ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="flex space-x-3 mt-3">
                                        <button onclick="buyNow(<?php echo $item['product_id']; ?>, this)" 
                                                class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg text-sm font-medium transition-colors">
                                            <i class="fas fa-bolt mr-1"></i> Buy Now
                                        </button>
                                        <button onclick="removeFromCart(<?php echo $item['product_id']; ?>, this)" 
                                                class="remove-btn text-red-600 hover:text-red-700 text-sm font-medium border border-red-200 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors">
                                            <i class="fas fa-trash-alt mr-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Price Summary -->
                    <div class="lg:w-1/3">
                        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                            <h3 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h3>
                            <div class="flex justify-between text-gray-600 mb-2">
                                <span>Subtotal (<span class="item-count"><?php echo $cart_count; ?> items</span>)</span>
                                <span class="subtotal">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600 mb-2">
                                <span>Shipping</span>
                                <span>Free</span>
                            </div>
                            <div class="border-t border-gray-200 mt-4 pt-4">
                                <div class="flex justify-between text-lg font-bold text-gray-900">
                                    <span>Total</span>
                                    <span class="total">$<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                            </div>
                            <a href="checkout.php" class="btn-primary w-full mt-6 block text-center">
                                Proceed to Checkout
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-50 text-gray-600 border-t border-gray-100">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-10">
                <div class="md:col-span-2">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-gamepad text-white"></i>
                        </div>
                        <span class="text-xl font-bold bg-gradient-to-r from-indigo-400 to-purple-500 bg-clip-text text-transparent">NexusGear</span>
                    </div>
                    <p class="text-gray-500 mb-6">Your ultimate destination for premium gaming accessories that elevate your gaming experience to the next level.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors">
                            <i class="fab fa-discord"></i>
                        </a>
                    </div>
                </div>
                <div>
                    <h4 class="text-gray-900 font-semibold text-lg mb-4">Shop</h4>
                    <ul class="space-y-2">
                        <li><a href="products.php" class="text-gray-600 hover:text-gray-900 transition-colors">All Products</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-gray-900 font-semibold text-lg mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-600 hover:text-gray-900 transition-colors">Contact Us</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-gray-900 font-semibold text-lg mb-4">Company</h4>
                    <ul class="space-y-2">
                        <li><a href="homepage.php#about" class="text-gray-600 hover:text-gray-900 transition-colors">About Us</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-100 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm mb-4 md:mb-0">© 2025 NexusGear. All rights reserved.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-gray-600 text-sm transition-colors">Privacy Policy</a>
                    <a href="#" class="text-gray-400 hover:text-gray-600 text-sm transition-colors">Terms of Service</a>
                    <a href="#" class="text-gray-400 hover:text-gray-600 text-sm transition-colors">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Profile dropdown toggle
            const profileDropdownButton = document.getElementById('profileDropdownButton');
            const profileDropdown = document.getElementById('profileDropdown');
            if (profileDropdownButton && profileDropdown) {
                profileDropdownButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const isExpanded = profileDropdownButton.getAttribute('aria-expanded') === 'true';
                    profileDropdownButton.setAttribute('aria-expanded', !isExpanded);
                    profileDropdown.classList.toggle('hidden');
                });
                document.addEventListener('click', function(e) {
                    if (!profileDropdownButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                        profileDropdownButton.setAttribute('aria-expanded', 'false');
                    }
                });
                profileDropdown.querySelectorAll('a, button').forEach(item => {
                    item.addEventListener('click', function() {
                        profileDropdown.classList.add('hidden');
                        profileDropdownButton.setAttribute('aria-expanded', 'false');
                    });
                });
            }

            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const mobileMenu = document.getElementById('mobileMenu');
            const closeMobileMenuButton = document.getElementById('closeMobileMenu');
            if (mobileMenuButton && mobileMenu && closeMobileMenuButton) {
                mobileMenuButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    mobileMenu.classList.toggle('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', mobileMenu.classList.contains('hidden') ? 'false' : 'true');
                });
                closeMobileMenuButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                });
                mobileMenu.querySelectorAll('a, button').forEach(item => {
                    item.addEventListener('click', function() {
                        mobileMenu.classList.add('hidden');
                        mobileMenuButton.setAttribute('aria-expanded', 'false');
                    });
                });
                document.addEventListener('click', function(e) {
                    if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                        mobileMenu.classList.add('hidden');
                        mobileMenuButton.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });

        // Update quantity via AJAX (unchanged)
        function updateQuantity(productId, quantity) {
            const select = document.getElementById(`quantity-${productId}`);
            const originalValue = select.value;
            select.disabled = true;
            
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `product_id=${productId}&quantity=${quantity}&update=true`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.cart_count !== undefined) {
                        document.querySelector('.cart-badge').textContent = data.cart_count;
                    }
                    if (data.subtotal !== undefined) {
                        document.querySelectorAll('.subtotal, .total').forEach(el => {
                            el.textContent = `$${parseFloat(data.subtotal).toFixed(2)}`;
                        });
                    }
                    document.querySelectorAll('.item-count').forEach(el => {
                        el.textContent = `${data.cart_count || 0} items`;
                    });
                    if (data.removed) {
                        location.reload();
                    }
                } else {
                    select.value = originalValue;
                    alert(data.message || 'Failed to update quantity');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                select.value = originalValue;
                alert('An error occurred while updating quantity');
            })
            .finally(() => {
                select.disabled = false;
            });
        }

        // Remove item from cart via AJAX
        function removeFromCart(productId, button) {
            if (!confirm('Are you sure you want to remove this item?')) return;

            // Disable the button and show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Removing...';

            // Use relative path and log the full URL for debugging
            const url = 'update_cart.php'; // Adjust if in a subdirectory
            console.log('Requesting:', window.location.origin + '/' + url);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `product_id=${productId}&action=remove`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Remove item from DOM with fade-out
                    const itemElement = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                    if (itemElement) {
                        itemElement.style.transition = 'opacity 0.3s ease';
                        itemElement.style.opacity = '0';
                        setTimeout(() => itemElement.remove(), 300);
                    }

                    // Update cart count, subtotal, total, and item count
                    document.querySelector('.cart-badge').textContent = data.cart_count;
                    document.querySelectorAll('.subtotal').forEach(el => {
                        el.textContent = `$${parseFloat(data.subtotal).toFixed(2)}`;
                    });
                    document.querySelectorAll('.total').forEach(el => {
                        el.textContent = `$${parseFloat(data.subtotal).toFixed(2)}`;
                    });
                    document.querySelectorAll('.item-count').forEach(el => {
                        el.textContent = `${data.cart_count} items`;
                    });

                    // Show empty cart message if no items left
                    if (data.cart_count === 0) {
                        const cartSection = document.querySelector('.cart-section .container');
                        cartSection.innerHTML = `
                            <div class="text-center py-12">
                                <i class="fas fa-shopping-cart text-5xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 text-lg mb-6">Your cart is empty</p>
                                <a href="products.php" class="btn-primary inline-block">Shop Now</a>
                            </div>
                        `;
                    }
                } else {
                    console.error('Server error:', data.message);
                    alert(data.message || 'Failed to remove item from cart');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An error occurred while removing item. Check console for details.');
            })
            .finally(() => {
                // Re-enable the button and restore text
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-trash-alt mr-1"></i> Remove';
            });
        }
    </script>
</body>
</html>