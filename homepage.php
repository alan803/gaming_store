<?php
session_start();
include 'connection.php';

// Fetch all active categories
$categories = [];
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
if ($result && $result->num_rows > 0) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}

// Define default categories if none exist
$defaultCategories = [
    ['name' => 'Keyboards', 'description' => 'Mechanical & RGB', 'icon' => 'keyboard'],
    ['name' => 'Mice', 'description' => 'Precision & Speed', 'icon' => 'mouse'],
    ['name' => 'Headsets', 'description' => 'Immersive Audio', 'icon' => 'headset'],
    ['name' => 'Controllers', 'description' => 'Wireless & Wired', 'icon' => 'gamepad']
];

// Use database categories if available, otherwise use defaults
$displayCategories = !empty($categories) ? $categories : $defaultCategories;

// Calculate initial cart count if logged in (count unique products)
$cart_count = 0;
if (isset($_SESSION['auth_user_id'])) {
    $user_id = $_SESSION['auth_user_id'];
    $count_query = "SELECT COUNT(*) as total_items FROM cart WHERE user_id = $user_id";
    $count_result = mysqli_query($conn, $count_query);
    if ($count_result) {
        $cart_count = mysqli_fetch_assoc($count_result)['total_items'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusGear - Ultimate Gaming Accessories</title>
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
        .category-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .category-card:hover {
            transform: translateY(-8px);
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
        .category-card h3, 
        .category-card .font-bold {
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
        .category-card {
            padding: 1.1rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-8px);
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
        /* Hide Add to Cart button by default and show on hover */
        .category-card .add-to-cart {
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            width: 100%;
            padding: 0.5rem 0;
        }
        .category-card:hover .add-to-cart {
            opacity: 1;
            transform: translateY(0);
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
                    <a href="" class="nav-link text-gray-600 hover:text-indigo-600">Home</a>
                    <a href="#about" class="nav-link text-gray-600 hover:text-indigo-600">About</a>
                    <a href="products.php" class="nav-link text-gray-600 hover:text-indigo-600">Shop</a>
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
                    <a href="" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Home</a>
                    <a href="#about" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">About</a>
                    <a href="#" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Products</a>
                    <a href="#" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Categories</a>
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
                            <a href="signup.php" class="block py-3 text-center bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-32 pb-24 overflow-hidden bg-gradient-to-br from-indigo-50 to-blue-50">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiB2aWV3Qm94PSIwIDAgMTAwIDEwMCIgcHJlc2VydmVBc3BlY3RSYXRpbz0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0dGVybiBpZD0icGF0dGVybi1iYWNrZ3JvdW5kIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxsaW5lIHgxPSIwIiB5PSIwIiB4Mj0iMCIgeTI9IjQwIiBzdHJva2U9InJnYmEoMTAyLCAxMjYsIDIzNCwgMC4xKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjcGF0dGVybi1iYWNrZ3JvdW5kKSIvPjwvc3ZnPg==')] bg-repeat"></div>
        </div>
        <div class="container mx-auto px-4 relative z-10">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-10 md:mb-0">
                    <span class="bg-indigo-100 text-indigo-700 text-sm font-medium px-3 py-1 rounded-full mb-4 inline-block">New Collection 2025</span>
                    <h1 class="text-4xl md:text-6xl font-bold mb-6 leading-tight">
                        <span class="text-gray-900">Elevate Your</span><br>
                        <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Gaming Experience</span>
                    </h1>
                    <p class="text-gray-600 text-lg mb-8 max-w-lg">Discover premium gaming accessories designed for professional gamers and enthusiasts alike. Experience the next level of gaming performance.</p>
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="products.php" class="btn-primary text-white font-medium py-3 px-8 rounded-full text-center hover:shadow-lg transition-all">
                            Shop Now
                        </a>
                        <a href="#" class="border border-indigo-600 text-indigo-600 hover:bg-indigo-50 font-medium py-3 px-8 rounded-full text-center transition-colors">
                            Explore
                        </a>
                    </div>
                    <div class="mt-10 flex items-center space-x-8">
                        <div class="text-center">
                            <?php
                                include 'connection.php';
                                $query = "SELECT COUNT(*) as total FROM users";
                                $result = $conn->query($query);
                                $row = $result->fetch_assoc();
                                $total_users = $row['total'];
                            ?>
                            <div class="text-2xl font-bold text-gray-900"><?php echo $total_users; ?></div>
                            <div class="text-sm text-gray-600">Happy Customers</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">5K+</div>
                            <div class="text-sm text-gray-600">5 Star Reviews</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">24/7</div>
                            <div class="text-sm text-gray-600">Support</div>
                        </div>
                    </div>
                </div>
                <div class="md:w-1/2 relative">
                    <div class="relative">
                        <div class="absolute -top-10 -left-10 w-40 h-40 bg-purple-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
                        <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-indigo-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
                        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-pink-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
                        <img src="background_img2.webp" 
                             alt="Gaming Setup" 
                             class="relative z-10 w-full max-w-2xl mx-auto rounded-lg shadow-xl border border-gray-100">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <section class="py-16 gradient-bg">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Shop by Category</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Explore our wide range of gaming accessories designed to enhance your gaming setup</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 px-2">
                <?php 
                $colors = ['indigo', 'purple', 'pink', 'blue', 'teal', 'amber', 'emerald', 'rose'];
                $icons = ['keyboard', 'mouse', 'headset', 'gamepad', 'microchip', 'tv', 'mobile', 'mouse-pointer'];
                
                foreach ($displayCategories as $index => $category): 
                    $color = $colors[$index % count($colors)];
                    $icon = $icons[$index % count($icons)];
                    $name = htmlspecialchars($category['name']);
                ?>
                <a href="products.php?category=<?php echo urlencode($category['name']); ?>" class="category-card group relative overflow-hidden rounded-2xl p-6 transition-all duration-300">
                    <div class="absolute inset-0 bg-gradient-to-br from-<?php echo $color; ?>-900/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative z-10 flex flex-col items-center">
                        <?php if (!empty($category['image'])): ?>
                            <div class="w-24 h-24 mb-4 rounded-2xl overflow-hidden bg-<?php echo $color; ?>-900/10 flex items-center justify-center group-hover:bg-<?php echo $color; ?>-600/10 transition-all duration-300 transform group-hover:scale-110">
                                <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo $name; ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                            </div>
                        <?php else: ?>
                            <div class="w-24 h-24 mb-4 rounded-2xl bg-gradient-to-br from-<?php echo $color; ?>-600 to-<?php echo $color; ?>-800 flex items-center justify-center transform transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg">
                                <i class="fas fa-<?php echo $icon; ?> text-4xl text-white opacity-90 group-hover:opacity-100 transition-opacity"></i>
                            </div>
                        <?php endif; ?>
                        <h3 class="font-bold text-lg text-gray-900 group-hover:text-<?php echo $color; ?>-600 transition-colors duration-300"><?php echo $name; ?></h3>
                        <span class="mt-1 inline-flex items-center text-sm text-<?php echo $color; ?>-400 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
                            View all
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-16 gradient-bg">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center mb-10">
                <div>
                    <h2 class="text-3xl font-bold mb-2">Featured Products</h2>
                    <p class="text-gray-600">Handpicked selection of premium gaming gear</p>
                </div>
                <a href="#" class="mt-4 md:mt-0 inline-flex items-center text-indigo-600 hover:text-indigo-500">
                    View All
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
            
            <?php
            // Fetch the 4 most recent products
            $product_query = "SELECT p.id, p.category_id, p.name, p.description, p.price, p.image_name, p.stock, c.name as category_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             ORDER BY p.id DESC LIMIT 4";
            $product_result = mysqli_query($conn, $product_query);
            
            if (mysqli_num_rows($product_result) > 0) {
                echo '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">';
                
                $colors = ['indigo', 'purple', 'pink', 'blue', 'teal', 'amber', 'emerald', 'rose'];
                $index = 0;
                
                while ($product = mysqli_fetch_assoc($product_result)) {
                    // Format price with 2 decimal places
                    $price = number_format($product['price'], 2);
                    // Get the image path
                    $main_image = !empty($product['image_name']) ? 'uploads/products/' . $product['image_name'] : 'images/default-product.jpg';
                    $color = $colors[$index % count($colors)];
                    $index++;
                    
                    // Check if product is in cart
                    $is_in_cart = false;
                    if (isset($_SESSION['auth_user_id'])) {
                        $user_id = $_SESSION['auth_user_id'];
                        $check_query = "SELECT * FROM cart WHERE user_id = $user_id AND product_id = {$product['id']}";
                        $check_result = mysqli_query($conn, $check_query);
                        if (mysqli_num_rows($check_result) > 0) {
                            $is_in_cart = true;
                        }
                    }
                    
                    echo '<div class="category-card group relative overflow-hidden rounded-2xl p-6 transition-all duration-300">';
                    echo '    <div class="absolute inset-0 bg-gradient-to-br from-' . $color . '-900/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>';
                    echo '    <div class="relative z-10 flex flex-col items-center">';
                    echo '        <div class="w-32 h-32 mb-6 rounded-2xl overflow-hidden bg-' . $color . '-900/10 flex items-center justify-center group-hover:bg-' . $color . '-600/10 transition-all duration-300 transform group-hover:scale-105">';
                    echo '            <img src="' . htmlspecialchars($main_image) . '" alt="' . htmlspecialchars($product['name']) . '" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">';
                    echo '        </div>';
                    echo '        <div class="flex items-center mb-1">';
                    echo '            <div class="text-yellow-400 mr-1">';
                    echo '                <i class="fas fa-star"></i>';
                    echo '                <i class="fas fa-star"></i>';
                    echo '                <i class="fas fa-star"></i>';
                    echo '                <i class="fas fa-star"></i>';
                    echo '                <i class="far fa-star"></i>';
                    echo '            </div>';
                    echo '            <span class="text-xs text-gray-500">(0)</span>';
                    echo '        </div>';
                    echo '        <h3 class="font-bold text-lg text-gray-900 group-hover:text-' . $color . '-600 transition-colors duration-300">' . htmlspecialchars($product['name']) . '</h3>';
                    echo '        <div class="flex justify-between items-center w-full mt-2">';
                    echo '            <span class="text-lg font-bold text-gray-900">$' . $price . '</span>';
                    echo '            <button class="text-gray-400 hover:text-red-500 transition-colors">';
                    echo '                <i class="far fa-heart group-hover:scale-110 transition-transform"></i>';
                    echo '            </button>';
                    echo '        </div>';
                    echo '        <div id="cart-message-' . $product['id'] . '" class="mt-2 text-sm"></div>';
                    echo '        <span class="mt-1 inline-flex items-center text-sm text-' . $color . '-400 opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">';
                    echo '            View details';
                    echo '            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                    echo '                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />';
                    echo '            </svg>';
                    echo '        </span>';
                    // Display "Go to Cart" or "Add to Cart" based on cart status
                    if ($is_in_cart) {
                        echo '        <a href="cart.php" class="add-to-cart mt-2 w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm font-medium transition-colors">';
                        echo '            <span>Go to Cart</span>';
                        echo '        </a>';
                    } else {
                        echo '        <button onclick="addToCart(' . $product['id'] . ')" class="add-to-cart mt-2 w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition-colors">';
                        echo '            <span>Add to Cart</span>';
                        echo '        </button>';
                    }
                    echo '    </div>';
                    echo '</div>';
                }
                
                echo '</div>'; // Close grid
            } else {
                echo '<p class="text-gray-600 text-center py-8">No products found. Please check back later.</p>';
            }
            ?>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-10 md:mb-0 md:pr-10">
                    <div class="relative rounded-2xl overflow-hidden shadow-xl">
                        <img src="about_us.webp" 
                             alt="Our Gaming Store" 
                             class="w-full h-96 object-cover rounded-2xl transform hover:scale-105 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent rounded-2xl"></div>
                    </div>
                </div>
                <div class="md:w-1/2">
                    <span class="text-indigo-600 font-semibold mb-3 inline-block">ABOUT NEXUSGEAR</span>
                    <h2 class="text-3xl md:text-4xl font-bold mb-6">Your Ultimate Gaming Experience Starts Here</h2>
                    <p class="text-gray-600 mb-6">At NexusGear, we're passionate about gaming and dedicated to providing the highest quality gaming accessories to enhance your gameplay. Founded in 2020, we've grown from a small startup to a leading destination for gamers worldwide.</p>
                    
                    <div class="grid grid-cols-2 gap-6 mb-8">
                        <div class="flex items-start space-x-3">
                            <div class="bg-indigo-100 p-2 rounded-full">
                                <i class="fas fa-trophy text-indigo-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Premium Quality</h4>
                                <p class="text-sm text-gray-500">Top-tier gaming gear</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-indigo-100 p-2 rounded-full">
                                <i class="fas fa-shipping-fast text-indigo-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Fast Shipping</h4>
                                <p class="text-sm text-gray-500">Worldwide delivery</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-indigo-100 p-2 rounded-full">
                                <i class="fas fa-headset text-indigo-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">24/7 Support</h4>
                                <p class="text-sm text-gray-500">Dedicated team</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-indigo-100 p-2 rounded-full">
                                <i class="fas fa-shield-alt text-indigo-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold">Secure Payment</h4>
                                <p class="text-sm text-gray-500">100% secure checkout</p>
                            </div>
                        </div>
                    </div>
                    
                    <a href="#" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-6 rounded-lg transition-all duration-300 transform hover:-translate-y-1">
                        Learn More About Us
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 relative overflow-hidden bg-gradient-to-r from-indigo-600 to-blue-600 text-white">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiB2aWV3Qm94PSIwIDAgMTAwIDEwMCIgcHJlc2VydmVBc3BlY3RSYXRpbz0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0dGVybiBpZD0icGF0dGVybi1iYWNrZ3JvdW5kIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxsaW5lIHgxPSIwIiB5PSIwIiB4Mj0iMCIgeTI9IjQwIiBzdHJva2U9InJnYmEoMjU1LCAyNTUsIDI1NSwgMC4xNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI3BhdHRlcm4tYmFja2dyb3VuZCkiLz48L3N2Zz4=')] bg-repeat"></div>
        </div>
        <div class="absolute inset-0">
            <img src="https://images.unsplash.com/photo-1511512578047-dfb367046420?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80" 
                 alt="Gaming Setup" 
                 class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-indigo-900/90 to-purple-900/90"></div>
        </div>
        <div class="relative z-10">
            <div class="container mx-auto px-4 text-center">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to Upgrade Your Gaming Setup?</h2>
                <p class="text-lg text-gray-300 max-w-2xl mx-auto mb-8">Join thousands of gamers who have already enhanced their gaming experience with our premium accessories.</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="#" class="bg-white text-indigo-900 hover:bg-gray-100 font-medium py-3 px-8 rounded-full transition-colors">
                        Shop Now
                    </a>
                    <a href="#" class="border border-white/30 text-white hover:bg-white/10 font-medium py-3 px-8 rounded-full transition-colors">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="py-16 gradient-bg">
        <div class="container mx-auto px-4">
            <div class="max-w-3xl mx-auto bg-white rounded-2xl p-8 md:p-10 text-center border border-gray-200/50">
                <div class="w-16 h-16 bg-indigo-600/20 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-envelope-open-text text-2xl text-indigo-400"></i>
                </div>
                <h2 class="text-2xl md:text-3xl font-bold mb-4">Stay Updated</h2>
                <p class="text-gray-600 mb-8 max-w-lg mx-auto">Subscribe to our newsletter for the latest products, exclusive deals, and gaming news delivered to your inbox.</p>
                <form class="flex flex-col sm:flex-row gap-4 max-w-lg mx-auto">
                    <input type="email" 
                           placeholder="Enter your email" 
                           class="flex-grow px-5 py-3 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 placeholder-gray-400">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-6 rounded-lg transition-colors">
                        Subscribe
                    </button>
                </form>
                <p class="text-xs text-gray-500 mt-4">We respect your privacy. Unsubscribe at any time.</p>
            </div>
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
                        <li><a href="#" class="text-gray-600 hover:text-gray-900 transition-colors">All Products</a></li>
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
                        <li><a href="#" class="text-gray-600 hover:text-gray-900 transition-colors">About Us</a></li>
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

            // Add to cart function with AJAX
            window.addToCart = function(productId) {
                const button = event.currentTarget;
                fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart badge
                        const cartBadge = document.querySelector('.cart-badge');
                        cartBadge.textContent = data.cart_count;

                        // Animation
                        const originalText = button.querySelector('span').textContent.trim();
                        button.querySelector('span').textContent = 'Added!';
                        button.classList.remove('bg-indigo-600');
                        button.classList.add('bg-green-500');

                        setTimeout(() => {
                            // Change to Go to Cart link
                            button.outerHTML = `<a href="cart.php" class="add-to-cart mt-2 w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm font-medium transition-colors"><span>Go to Cart</span></a>`;
                        }, 1500);
                    } else {
                        if (data.message === 'Please login to add items to cart') {
                            window.location.href = 'login.php';
                        } else {
                            const messageDiv = document.getElementById(`cart-message-${productId}`);
                            messageDiv.textContent = data.message;
                            messageDiv.className = 'mt-2 text-sm text-red-600';
                            setTimeout(() => {
                                messageDiv.textContent = '';
                            }, 3000);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const messageDiv = document.getElementById(`cart-message-${productId}`);
                    messageDiv.textContent = 'An error occurred';
                    messageDiv.className = 'mt-2 text-sm text-red-600';
                    setTimeout(() => {
                        messageDiv.textContent = '';
                    }, 3000);
                });
            };

            // Wishlist toggle
            const wishlistButtons = document.querySelectorAll('.category-card .fa-heart');
            wishlistButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.toggle('far');
                    this.classList.toggle('fas');
                    this.classList.toggle('text-red-500');
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
    </script>
</body>
</html>