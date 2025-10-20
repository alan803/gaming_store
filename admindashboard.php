<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['auth_user_id']) || !isset($_SESSION['auth_admin_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'connection.php';

// Get admin details
$admin_id = $_SESSION['auth_admin_id'];
$admin_username = $_SESSION['auth_username'] ?? 'Admin';
<<<<<<< HEAD

// Dashboard metrics
$total_sales = 0.0;
$total_orders = 0;
$total_products = 0;

// Total sales (paid orders)
$q1 = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total_sales FROM orders WHERE payment_status = 'paid'");
if ($q1 && $q1->num_rows > 0) {
    $total_sales = (float) ($q1->fetch_assoc()['total_sales'] ?? 0);
}

// Total orders
$q2 = $conn->query("SELECT COUNT(*) AS total_orders FROM orders");
if ($q2 && $q2->num_rows > 0) {
    $total_orders = (int) ($q2->fetch_assoc()['total_orders'] ?? 0);
}

// Total products
$q3 = $conn->query("SELECT COUNT(*) AS total_products FROM products");
if ($q3 && $q3->num_rows > 0) {
    $total_products = (int) ($q3->fetch_assoc()['total_products'] ?? 0);
}
=======
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --dark: #111827;
            --content: #f9fafb;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background-color: #ffffff;
            color: #374151;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e5e7eb;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-collapsed {
            width: 80px;
        }
        
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        .main-content.expanded {
            margin-left: 80px;
        }
        
        .logo-container {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .nav-menu {
            list-style: none;
            padding: 1rem 0.75rem;
            margin: 0;
            flex: 1;
        }
        
        .nav-item {
            margin-bottom: 0.25rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #6b7280;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            font-size: 0.9375rem;
            font-weight: 500;
            position: relative;
        }
        
        .nav-link:hover {
            background-color: #f3f4f6;
            color: #111827;
        }
        
        .nav-link.active {
            background-color: #eef2ff;
            color: #4f46e5;
        }
        
        .nav-link i {
            width: 1.25rem;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1.125rem;
            flex-shrink: 0;
        }
        
        .nav-link:hover i,
        .nav-link.active i {
            color: #4f46e5;
        }
        
        .notification-badge {
            margin-left: auto;
            background-color: #4f46e5;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
        }
        
        .sidebar-collapsed .nav-text,
        .sidebar-collapsed .notification-badge,
        .sidebar-collapsed .logo-text {
            display: none;
        }
        
        .sidebar-collapsed .nav-link {
            justify-content: center;
            padding: 0.75rem;
        }
        
        .sidebar-collapsed .nav-link i {
            margin-right: 0;
        }
        
        .hamburger {
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0.375rem;
            border-radius: 0.375rem;
        }
        
        .hamburger:hover {
            background-color: #f3f4f6;
            color: #111827;
        }
        
        .sidebar-footer {
            border-top: 1px solid #e5e7eb;
            padding: 1rem;
        }
        
        .sidebar-footer a {
            display: flex;
            align-items: center;
            padding: 0.625rem 0.75rem;
            color: #6b7280;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .sidebar-footer a:hover {
            background-color: #f3f4f6;
            color: #111827;
        }
        
        .sidebar-footer .icon-box {
            width: 2.25rem;
            height: 2.25rem;
            background-color: #f3f4f6;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
        }
        
        .sidebar-collapsed .sidebar-footer .nav-text {
            display: none;
        }
        
        /* Scrollbar styling */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 10px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background-color: #9ca3af;
        }
        
        /* Tooltip for collapsed state */
        .tooltip {
            position: absolute;
            left: 100%;
            margin-left: 0.5rem;
            padding: 0.5rem 0.75rem;
            background-color: #1f2937;
            color: white;
            font-size: 0.875rem;
            border-radius: 0.375rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1000;
            pointer-events: none;
        }
        
        .sidebar-collapsed .nav-link:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 2.5rem; height: 2.5rem; background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <i class="fas fa-shield-alt" style="color: white; font-size: 1.125rem;"></i>
                    </div>
                    <div class="logo-text">
                        <div style="font-weight: 600; color: #111827; font-size: 1.125rem; line-height: 1.2;">NexusGear</div>
                        <div style="font-size: 0.75rem; color: #6b7280;">Admin Panel</div>
                    </div>
                </div>
                <button id="toggleSidebar" class="hamburger">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="#" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                    <span class="tooltip">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="add_product.php" class="nav-link">
                    <i class="fas fa-box"></i>
<<<<<<< HEAD
                    <span class="nav-text">Add Product</span>
                    <span class="tooltip">Add Product</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_product.php" class="nav-link">
                    <i class="fas fa-cogs"></i>
                    <span class="nav-text">Manage Products</span>
                    <span class="tooltip">Manage Products</span>
=======
                    <span class="nav-text">Products</span>
                    <span class="tooltip">Products</span>
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
                </a>
            </li>
            <li class="nav-item">
                <a href="add_category.php" class="nav-link">
                    <i class="fas fa-tags"></i>
                    <span class="nav-text">Categories</span>
                    <span class="tooltip">Categories</span>
                </a>
            </li>
            <li class="nav-item">
<<<<<<< HEAD
                <a href="admin_order_manage.php" class="nav-link">
=======
                <a href="#" class="nav-link">
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
                    <i class="fas fa-shopping-cart"></i>
                    <span class="nav-text">Orders</span>
                    <span class="notification-badge">5</span>
                    <span class="tooltip">Orders</span>
                </a>
            </li>
<<<<<<< HEAD
        </ul>
=======
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">Users</span>
                    <span class="tooltip">Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span class="nav-text">Analytics</span>
                    <span class="tooltip">Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Settings</span>
                    <span class="tooltip">Settings</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="homepage.php">
                <div class="icon-box">
                    <i class="fas fa-arrow-left" style="color: #6b7280; font-size: 0.875rem;"></i>
                </div>
                <span class="nav-text" style="font-weight: 500; font-size: 0.875rem;">Back to Store</span>
            </a>
        </div>
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <header style="background-color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h1 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">Dashboard Overview</h1>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem;">
<<<<<<< HEAD
=======
                    <button style="padding: 0.5rem; color: #6b7280; border-radius: 9999px; border: none; background: transparent; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                        <i class="fas fa-bell"></i>
                    </button>
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
                    
                    <div style="position: relative;">
                        <button id="userMenuButton" style="display: flex; align-items: center; gap: 0.5rem; border: none; background: transparent; cursor: pointer;">
                            <div style="width: 2rem; height: 2rem; border-radius: 9999px; background-color: #eef2ff; display: flex; align-items: center; justify-content: center; color: #4f46e5; font-weight: 500;">
                                <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                            </div>
                            <span style="font-size: 0.875rem; font-weight: 500; color: #374151;"><?php echo htmlspecialchars($admin_username); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        <div id="userMenu" style="display: none; position: absolute; right: 0; margin-top: 0.5rem; width: 12rem; background-color: white; border-radius: 0.375rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 0.25rem 0; z-index: 50; border: 1px solid #e5e7eb;">
<<<<<<< HEAD
=======
                            <a href="#" style="display: block; padding: 0.5rem 1rem; font-size: 0.875rem; color: #374151; text-decoration: none;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                                <i class="fas fa-user" style="margin-right: 0.5rem;"></i>Profile
                            </a>
                            <a href="#" style="display: block; padding: 0.5rem 1rem; font-size: 0.875rem; color: #374151; text-decoration: none;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                                <i class="fas fa-cog" style="margin-right: 0.5rem;"></i>Settings
                            </a>
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
                            <div style="border-top: 1px solid #f3f4f6; margin: 0.25rem 0;"></div>
                            <form action="logout.php" method="post" style="width: 100%; margin: 0;">
                                <button type="submit" style="width: 100%; text-align: left; padding: 0.5rem 1rem; font-size: 0.875rem; color: #dc2626; border: none; background: transparent; cursor: pointer;" onmouseover="this.style.backgroundColor='#fef2f2'" onmouseout="this.style.backgroundColor='transparent'">
                                    <i class="fas fa-sign-out-alt" style="margin-right: 0.5rem;"></i>Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- Stats Cards -->
                <div style="background-color: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; border: 1px solid #f3f4f6;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin: 0 0 0.5rem 0;">Total Sales</p>
<<<<<<< HEAD
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;">$<?php echo number_format($total_sales, 2); ?></h3>
=======
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;">$24,780</h3>
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
                        </div>
                        <div style="padding: 0.75rem; background-color: #eef2ff; border-radius: 0.5rem; color: #4f46e5;">
                            <i class="fas fa-dollar-sign" style="font-size: 1.25rem;"></i>
                        </div>
                    </div>
                </div>
                
                <div style="background-color: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; border: 1px solid #f3f4f6;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin: 0 0 0.5rem 0;">Total Orders</p>
<<<<<<< HEAD
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo number_format($total_orders); ?></h3>
=======
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;">1,284</h3>
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
                        </div>
                        <div style="padding: 0.75rem; background-color: #dcfce7; border-radius: 0.5rem; color: #16a34a;">
                            <i class="fas fa-shopping-bag" style="font-size: 1.25rem;"></i>
                        </div>
                    </div>
                </div>
                
                <div style="background-color: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; border: 1px solid #f3f4f6;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin: 0 0 0.5rem 0;">Total Products</p>
<<<<<<< HEAD
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo number_format($total_products); ?></h3>
=======
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;">487</h3>
>>>>>>> 22f2b8c22004664ef3f98ffefaf8f58b81b31de9
                        </div>
                        <div style="padding: 0.75rem; background-color: #dbeafe; border-radius: 0.5rem; color: #2563eb;">
                            <i class="fas fa-box" style="font-size: 1.25rem;"></i>
                        </div>
                    </div>
                </div>
                
                <div style="background-color: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; border: 1px solid #f3f4f6;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <p style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin: 0 0 0.5rem 0;">Total Customers</p>
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;">
                            <?php
                                $query = "SELECT COUNT(*) as total FROM users";
                                $result = $conn->query($query);
                                $row = $result->fetch_assoc();
                                echo $row['total'];
                            ?>
                            </h3>
                        </div>
                        <div style="padding: 0.75rem; background-color: #f3e8ff; border-radius: 0.5rem; color: #9333ea;">
                            <i class="fas fa-users" style="font-size: 1.25rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders Table -->
            <div style="background-color: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #f3f4f6;">
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f3f4f6;">
                    <h3 style="font-size: 1.125rem; font-weight: 500; color: #111827; margin: 0;">Recent Orders</h3>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f9fafb;">
                            <tr>
                                <th style="padding: 0.75rem 1.5rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Order ID</th>
                                <th style="padding: 0.75rem 1.5rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Customer</th>
                                <th style="padding: 0.75rem 1.5rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;"><a href="add_product.php" style="color: inherit; text-decoration: none;">Products</a></th>
                                <th style="padding: 0.75rem 1.5rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Total</th>
                                <th style="padding: 0.75rem 1.5rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                                <th style="padding: 0.75rem 1.5rem; text-align: right; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Action</th>
                            </tr>
                        </thead>
                        <tbody style="background-color: white;">
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 1rem 1.5rem; white-space: nowrap; font-size: 0.875rem; font-weight: 500; color: #111827;">#ORD-7841</td>
                                <td style="padding: 1rem 1.5rem; white-space: nowrap; font-size: 0.875rem; color: #6b7280;">John Doe</td>
                                <td style="padding: 1rem 1.5rem; white-space: nowrap; font-size: 0.875rem; color: #6b7280;">3 items</td>
                                <td style="padding: 1rem 1.5rem; white-space: nowrap; font-size: 0.875rem; color: #111827;">$249.99</td>
                                <td style="padding: 1rem 1.5rem; white-space: nowrap;">
                                    <span style="padding: 0.125rem 0.5rem; display: inline-flex; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; background-color: #dcfce7; color: #166534;">
                                        Completed
                                    </span>
                                </td>
                                <td style="padding: 1rem 1.5rem; white-space: nowrap; text-align: right; font-size: 0.875rem; font-weight: 500;">
                                    <a href="#" style="color: #4f46e5; text-decoration: none;">View</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="padding: 1rem 1.5rem; border-top: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between;">
                    <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Showing <span style="font-weight: 500;">1</span> to <span style="font-weight: 500;">5</span> of <span style="font-weight: 500;">24</span> orders</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <button style="padding: 0.25rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; color: #374151; background-color: white; cursor: pointer;">
                            Previous
                        </button>
                        <button style="padding: 0.25rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; color: white; background-color: #4f46e5; cursor: pointer;">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        let isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        // Set initial state
        if (isCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.add('expanded');
        }
        
        toggleSidebar.addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('expanded');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
        
        // Toggle user menu
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenu = document.getElementById('userMenu');
        
        userMenuButton.addEventListener('click', () => {
            userMenu.style.display = userMenu.style.display === 'none' ? 'block' : 'none';
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.style.display = 'none';
            }
        });
    </script>
</body>
</html>