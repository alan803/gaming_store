<?php
session_start();
include 'connection.php';

// Fetch all active categories
$categories = [];
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
if ($result && $result->num_rows > 0) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}

// Pagination
$products_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $products_per_page;

// Get total number of products
$total_products_query = "SELECT COUNT(*) as total FROM products";
$stmt = $conn->prepare($total_products_query);
$stmt->execute();
$total_products_result = $stmt->get_result();
$total_products = $total_products_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $products_per_page);
$stmt->close();

// Build base query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE 1=1";
$params = [];
$types = '';

// Add search condition
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $query .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.name DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY p.id DESC";
        break;
}

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $products_per_page;
$params[] = $offset;
$types .= 'ii';

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$product_result = $stmt->get_result();

// Calculate cart count if logged in
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
    <title>All Products - NexusGear</title>
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
        
        .product-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        .product-card:hover {
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
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px -5px rgba(79, 70, 229, 0.4);
        }
        
        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            font-weight: 500;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        
        .nav-link {
            position: relative;
            font-weight: 500;
            color: #4b5563;
            transition: all 0.3s ease;
        }
        
        .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover:after {
            width: 100%;
        }
        
        .nav-link.active {
            color: var(--primary);
        }
        
        .nav-link.active:after {
            width: 100%;
        }
        
        .mobile-menu {
            transition: transform 0.3s ease-in-out;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            color: #4b5563;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .page-link:hover {
            background-color: #f3f4f6;
            color: var(--primary);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary);
            color: white;
        }
        
        .page-item.disabled .page-link {
            color: #9ca3af;
            pointer-events: none;
        }
        
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background-color: white !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        section:first-of-type {
            margin-top: 50px;
            padding-top: 2rem;
        }

        .dropdown-menu {
            transition: all 0.2s ease;
            transform-origin: top right;
            min-width: 12rem;
            border-radius: 0.5rem;
        }

        .dropdown-menu.hidden {
            transform: scaleY(0);
            opacity: 0;
            pointer-events: none;
        }

        .dropdown-menu:not(.hidden) {
            transform: scaleY(1);
            opacity: 1;
            pointer-events: auto;
        }

        /* Hide Add to Cart button by default and show on hover */
        .product-card .add-to-cart {
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

        .product-card:hover .add-to-cart {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            section:first-of-type {
                margin-top: 30px;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation (Same as homepage.php) -->
    <nav class="fixed w-full z-50 bg-white shadow-sm">
        <div class="container mx-auto px-4 py-3" style="max-width: 1280px;">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="logo-icon bg-indigo-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-gamepad text-white"></i>
                    </div>
                    <a href="homepage.php" class="text-2xl font-bold text-gray-800">Nexus<span class="text-indigo-600">Gear</span></a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="homepage.php" class="nav-link">Home</a>
                    <a href="products.php" class="nav-link active">Shop</a>
                    <a href="homepage.php#about" class="nav-link">About</a>
                </div>
                
                <div class="flex items-center space-x-6">
                    <!-- Cart Icon -->
                    <a href="cart.php" class="relative text-gray-600 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge absolute -top-2 -right-2 bg-indigo-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- User Profile / Login -->
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
                            <a href="login.php" class="text-gray-600 hover:text-indigo-600 transition-colors text-sm font-medium">
                                Sign In
                            </a>
                            <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                Sign Up
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Mobile Menu Button -->
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
                    <a href="products.php" class="block py-3 text-indigo-600 font-medium text-base border-b border-gray-100">Shop</a>
                    <a href="homepage.php#about" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">About</a>
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
    
    <!-- Hero Section -->
    <section class="gradient-bg">
        <div class="container mx-auto px-4" style="max-width: 1280px;">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold mb-3">Our Products</h1>
                <p class="text-gray-600 max-w-2xl mx-auto mb-8">Explore our wide range of gaming accessories and gear</p>
                
                <!-- Search and Sort -->
                <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                    <!-- Search Bar -->
                    <div class="w-full md:w-1/3">
                        <form action="products.php" method="GET" class="relative">
                            <input 
                                type="text" 
                                name="search" 
                                placeholder="Search products..." 
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <a href="products.php" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Sort Dropdown -->
                    <div class="w-full md:w-1/4">
                        <form action="products.php" method="GET" id="sortForm" class="w-full">
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                            <?php endif; ?>
                            <div class="relative">
                                <select 
                                    name="sort" 
                                    onchange="document.getElementById('sortForm').submit()"
                                    class="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 appearance-none bg-white"
                                >
                                    <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="name_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'name_asc') ? 'selected' : ''; ?>>Name: A to Z</option>
                                    <option value="name_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'name_desc') ? 'selected' : ''; ?>>Name: Z to A</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Product Info -->
            <div class="mb-8 flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <span class="text-sm text-gray-500">Showing <?php echo ($offset + 1) . ' - ' . min($offset + $products_per_page, $total_products); ?> of <?php echo $total_products; ?> products</span>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 pb-16">
                <?php
                $colors = ['indigo', 'purple', 'pink', 'blue', 'teal', 'amber', 'emerald', 'rose'];
                $index = 0;
                
                if ($product_result->num_rows > 0) {
                    while ($product = $product_result->fetch_assoc()) {
                        $price = number_format($product['price'], 2);
                        $main_image = !empty($product['image_name']) ? 'uploads/products/' . $product['image_name'] : 'images/default-product.jpg';
                        $color = $colors[$index % count($colors)];
                        $index++;
                        
                        // Check if product is in cart
                        $is_in_cart = false;
                        if (isset($_SESSION['auth_user_id'])) {
                            $user_id = $_SESSION['auth_user_id'];
                            $check_query = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
                            $stmt = $conn->prepare($check_query);
                            $stmt->bind_param("ii", $user_id, $product['id']);
                            $stmt->execute();
                            $check_result = $stmt->get_result();
                            $is_in_cart = $check_result->num_rows > 0;
                            $stmt->close();
                        }
                        ?>
                        <div class="product-card group relative overflow-hidden rounded-2xl p-6 transition-all duration-300">
                            <div class="absolute inset-0 bg-gradient-to-br from-<?php echo $color; ?>-900/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            <div class="relative z-10 flex flex-col items-center">
                                <div class="w-32 h-32 mb-6 rounded-2xl overflow-hidden bg-<?php echo $color; ?>-900/10 flex items-center justify-center group-hover:bg-<?php echo $color; ?>-600/10 transition-all duration-300 transform group-hover:scale-105">
                                    <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                                </div>
                                <div class="flex items-center mb-1">
                                    <div class="text-yellow-400 mr-1">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="far fa-star"></i>
                                    </div>
                                    <span class="text-xs text-gray-500">(0)</span>
                                </div>
                                <h3 class="font-bold text-lg text-gray-900 group-hover:text-<?php echo $color; ?>-600 transition-colors duration-300"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="flex justify-between items-center w-full mt-2">
                                    <span class="text-lg font-bold text-gray-900">$<?php echo $price; ?></span>
                                </div>
                                <div id="cart-message-<?php echo $product['id']; ?>" class="mt-2 text-sm"></div>
                                <?php if ($is_in_cart): ?>
                                    <a href="cart.php" class="add-to-cart mt-2 w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm font-medium transition-colors text-center">
                                        <span>Go to Cart</span>
                                    </a>
                                <?php else: ?>
                                    <button onclick="addToCart(<?php echo $product['id']; ?>, event)" class="add-to-cart mt-2 w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition-colors">
                                        <span>Add to Cart</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p class="col-span-full text-center py-12 text-gray-600">No products found. Please check back later.</p>';
                }
                ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-12 flex justify-center pb-16">
                    <nav class="pagination">
                        <ul class="flex items-center space-x-1">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?>" class="page-link" aria-label="Previous">
                                        <i class="fas fa-chevron-left text-sm"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($start > 1) {
                                echo '<li class="page-item"><a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($sort) ? '&sort=' . urlencode($sort) : '') . '" class="page-link">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?>" class="page-link"><?php echo $i; ?></a>
                                </li>
                            <?php
                            endfor;
                            
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($sort) ? '&sort=' . urlencode($sort) : '') . '" class="page-link">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?>" class="page-link" aria-label="Next">
                                        <i class="fas fa-chevron-right text-sm"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-50 text-gray-600 border-t border-gray-100">
        <div class="container mx-auto px-4 py-12" style="max-width: 1280px;">
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

                // Close mobile menu when clicking the close button
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

            // Add to cart function
            window.addToCart = function(productId, event) {
                const button = event.target.closest('button');
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
                        if (!cartBadge) {
                            const cartLink = document.querySelector('a[href="cart.php"]');
                            const badge = document.createElement('span');
                            badge.className = 'cart-badge absolute -top-2 -right-2 bg-indigo-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center';
                            badge.textContent = data.cart_count;
                            cartLink.appendChild(badge);
                        } else {
                            cartBadge.textContent = data.cart_count;
                        }

                        // Animation
                        const originalText = button.querySelector('span').textContent;
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
        });
    </script>
</body>
</html>