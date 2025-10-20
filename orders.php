<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['auth_user_id'])) {
    $_SESSION['login_redirect'] = 'orders.php';
    header('Location: login.php');
    exit();
}

$user_id = (int) $_SESSION['auth_user_id'];

// Fetch user's orders
$orders = [];
$stmt = $conn->prepare("SELECT order_id, total_amount, payment_status, order_status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Orders - NexusGear</title>
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
        .order-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .order-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.15);
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
        .order-card h3, 
        .order-card .font-bold {
            color: #111827 !important;
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
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1.25rem;
            }
        }
        .grid {
            display: grid;
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .grid {
                gap: 1.5rem;
            }
        }
        .order-card {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.15);
            border-color: var(--primary);
        }
        .btn-primary {
            padding: 0.875rem 2.25rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        @media (max-width: 767px) {
            .mobile-menu {
                transform: translateX(100%);
                transition: transform 0.3s ease-in-out;
            }
            .mobile-menu:not(.hidden) {
                transform: translateX(0);
            }
        }
        .badge-paid {
            background: #ecfdf5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .badge-other {
            background: #fff7ed;
            color: #92400e;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .status-pill {
            background: #f3f4f6;
            color: #374151;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .muted {
            color: var(--gray);
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
                    <a href="products.php" class="nav-link text-gray-600 hover:text-indigo-600">Shop</a>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="cart.php" class="text-gray-600 hover:text-indigo-600 relative transition-colors">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span class="cart-badge absolute -top-2 -right-2 bg-indigo-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
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
                                <a href="user_profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
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
                            <a href="login.php" class="text-gray-600 hover:text-indigo-600 transition-colors text-sm font-medium">
                                Sign In
                            </a>
                            <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                Sign Up
                            </a>
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
                    <a href="#" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Categories</a>
                    <?php if (isset($_SESSION['auth_user_id'])): ?>
                        <div class="mt-6 pt-6 border-t-2 border-gray-200">
                            <p class="text-sm text-gray-500 mb-4">Account</p>
                            <a href="user_profile.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium">
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
                            <a href="signup.php" class="block py-3 text-center bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <section class="relative pt-32 pb-24 overflow-hidden gradient-bg">
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                    <span class="text-gray-900">Your</span>
                    <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Orders</span>
                </h1>
                <p class="text-gray-600 text-lg max-w-2xl mx-auto">Track and manage all your gaming gear purchases</p>
            </div>

            <?php if (empty($orders)): ?>
                <div class="order-card text-center max-w-md mx-auto">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-shopping-bag text-2xl text-indigo-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4 text-gray-900">No Orders Yet</h3>
                    <p class="text-gray-600 mb-6">You haven't placed any orders yet. Start shopping to see your orders here!</p>
                    <a href="products.php" class="btn-primary text-white font-medium py-3 px-8 rounded-full text-center hover:shadow-lg transition-all">
                        Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="grid gap-6 max-w-4xl mx-auto">
                    <?php foreach ($orders as $o): ?>
                        <div class="order-card group relative overflow-hidden">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-indigo-50 to-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-lg group-hover:from-indigo-100 group-hover:to-indigo-200 transition-all duration-300">
                                        #<?php echo htmlspecialchars(substr($o['order_id'], -6)); ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h3 class="text-xl font-semibold text-gray-900">Order <?php echo htmlspecialchars($o['order_id']); ?></h3>
                                            <span class="muted text-sm bg-gray-100 px-3 py-1 rounded-full"><?php echo date('M j, Y', strtotime($o['created_at'])); ?></span>
                                        </div>
                                        <div class="text-gray-600">
                                            <span class="font-medium text-lg">₹<?php echo number_format((float)$o['total_amount'], 2); ?></span>
                                            <span class="text-sm ml-2">Total Amount</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 md:mt-0 flex items-center space-x-4">
                                    <div>
                                        <?php if ($o['payment_status'] === 'paid'): ?>
                                            <span class="badge-paid">
                                                <i class="fas fa-check-circle mr-1"></i>Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-other">
                                                <i class="fas fa-clock mr-1"></i><?php echo htmlspecialchars(ucfirst($o['payment_status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <span class="status-pill">
                                            <i class="fas fa-truck mr-1"></i><?php echo htmlspecialchars(ucfirst($o['order_status'])); ?>
                                        </span>
                                    </div>

                                    <div class="flex-shrink-0">
                                        <?php if (strtolower($o['order_status']) === 'processing'): ?>
                                            <button onclick="cancelOrder('<?php echo addslashes($o['order_id']); ?>', this)" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                                                <i class="fas fa-times mr-1"></i>Cancel
                                            </button>
                                        <?php else: ?>
                                            <span class="muted text-sm">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
                <p class="text-gray-400 text-sm mb-4 md:mb-0">© <?php echo date('Y'); ?> NexusGear. All rights reserved.</p>
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
                anchor.addEventListener('click', function (e) {
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

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!profileDropdownButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.add('hidden');
                        profileDropdownButton.setAttribute('aria-expanded', 'false');
                    }
                });

                // Close dropdown when a link or button inside is clicked
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

                // Close mobile menu when clicking outside
                closeMobileMenuButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                });

                // Close mobile menu when a link or button is clicked
                mobileMenu.querySelectorAll('a, button').forEach(item => {
                    item.addEventListener('click', function() {
                        mobileMenu.classList.add('hidden');
                        mobileMenuButton.setAttribute('aria-expanded', 'false');
                    });
                });

                // Close mobile menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                        mobileMenu.classList.add('hidden');
                        mobileMenuButton.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });

        async function cancelOrder(orderId, btn) {
            if (!confirm('Are you sure you want to cancel this order?')) return;
            btn.disabled = true;
            const prevText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Cancelling...';

            try {
                const resp = await fetch('cancel_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'order_id=' + encodeURIComponent(orderId)
                });
                const text = await resp.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { data = { success: false, message: text || 'Invalid server response' }; }

                if (resp.ok && data.success) {
                    // visually update order card
                    btn.closest('.order-card').querySelector('.status-pill').innerHTML = '<i class="fas fa-ban mr-1"></i>Cancelled';
                    btn.parentElement.innerHTML = '<span class="muted text-sm">Cancelled</span>';
                    
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                    successDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Order cancelled successfully';
                    document.body.appendChild(successDiv);
                    
                    setTimeout(() => {
                        successDiv.remove();
                    }, 3000);
                } else {
                    alert('Failed to cancel order: ' + (data.message || resp.status));
                    btn.disabled = false;
                    btn.innerHTML = prevText;
                }
            } catch (err) {
                console.error('cancelOrder error', err);
                alert('Network/server error while cancelling order.');
                btn.disabled = false;
                btn.innerHTML = prevText;
            }
        }
    </script>
</body>
</html>