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

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/products/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = trim($_POST['category_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval(trim($_POST['price']));
    $stock = intval(trim($_POST['stock']));
    $image_name = '';
    
    // Validate input
    $errors = [];
    
    if (empty($category_id)) {
        $errors[] = 'Category is required';
    }
    
    if (empty($name)) {
        $errors[] = 'Product name is required';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative';
    }
    
    // Handle file upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = uniqid('product_') . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                $image_name = $fileName;
            } else {
                $errors[] = 'Error uploading image';
            }
        } else {
            $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions);
        }
    } else {
        $errors[] = 'Product image is required';
    }
    
    if (empty($errors)) {
        // Check if product with same name already exists
        $stmt = $conn->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'A product with this name already exists';
            $message_type = 'error';
        } else {
            // Insert new product
            $stmt = $conn->prepare("INSERT INTO products (category_id, name, description, price, stock, image_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdis", $category_id, $name, $description, $price, $stock, $image_name);
            
            if ($stmt->execute()) {
                $message = 'Product added successfully';
                $message_type = 'success';
                // Clear form
                $_POST = array();
            } else {
                $message = 'Error adding product: ' . $conn->error;
                $message_type = 'error';
            }
        }
        $stmt->close();
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// Fetch all categories for the dropdown
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($result) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - NexusGear</title>
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
        
        .form-container {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: #fff;
            font-size: 0.875rem;
            line-height: 1.25;
            color: #111827;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
            line-height: 1.4;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.85rem;
            line-height: 1.2;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        
        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #f3f4f6;
            border: 1px dashed #d1d5db;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            text-align: center;
            width: 100%;
        }
        
        .file-input-label:hover {
            background-color: #e5e7eb;
        }
        
        .file-input {
            display: none;
        }
        
        .validation-error {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
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
                <a href="admindashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                    <span class="tooltip">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="add_product.php" class="nav-link active">
                    <i class="fas fa-box"></i>
                    <span class="nav-text">Products</span>
                    <span class="tooltip">Products</span>
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
                <a href="#" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="nav-text">Orders</span>
                    <span class="notification-badge">5</span>
                    <span class="tooltip">Orders</span>
                </a>
            </li>
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
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <header style="background-color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h1 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">Add New Product</h1>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button style="padding: 0.5rem; color: #6b7280; border-radius: 9999px; border: none; background: transparent; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                        <i class="fas fa-bell"></i>
                    </button>
                    
                    <div style="position: relative;">
                        <button id="userMenuButton" style="display: flex; align-items: center; gap: 0.5rem; border: none; background: transparent; cursor: pointer;">
                            <div style="width: 2rem; height: 2rem; border-radius: 9999px; background-color: #eef2ff; display: flex; align-items: center; justify-content: center; color: #4f46e5; font-weight: 500;">
                                <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                            </div>
                            <span style="font-size: 0.875rem; font-weight: 500; color: #374151;"><?php echo htmlspecialchars($admin_username); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        <div id="userMenu" style="display: none; position: absolute; right: 0; margin-top: 0.5rem; width: 12rem; background-color: white; border-radius: 0.375rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 0.25rem 0; z-index: 50; border: 1px solid #e5e7eb;">
                            <a href="#" style="display: block; padding: 0.5rem 1rem; font-size: 0.875rem; color: #374151; text-decoration: none;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                                <i class="fas fa-user" style="margin-right: 0.5rem;"></i>Profile
                            </a>
                            <a href="#" style="display: block; padding: 0.5rem 1rem; font-size: 0.875rem; color: #374151; text-decoration: none;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                                <i class="fas fa-cog" style="margin-right: 0.5rem;"></i>Settings
                            </a>
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
        <main style="padding: 1rem 1.5rem;">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-container">
                <h2 style="font-size: 1.1rem; font-weight: 600; color: #111827; margin-bottom: 1.25rem;">Add New Product</h2>
                
                <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                    <div class="form-group">
                        <label for="category_id" class="form-label">Category <span style="color: #ef4444;">*</span></label>
                        <select name="category_id" id="category_id" class="form-input" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="categoryError" class="validation-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="name" class="form-label">Product Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" id="name" class="form-input" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        <div id="nameError" class="validation-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-input form-textarea"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div id="descriptionError" class="validation-error"></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="price" class="form-label">Price ($) <span style="color: #ef4444;">*</span></label>
                            <input type="number" name="price" id="price" class="form-input" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                            <div id="priceError" class="validation-error"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock" class="form-label">Stock <span style="color: #ef4444;">*</span></label>
                            <input type="number" name="stock" id="stock" class="form-input" min="0" value="<?php echo htmlspecialchars($_POST['stock'] ?? '0'); ?>" required>
                            <div id="stockError" class="validation-error"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_image" class="form-label">Product Image <span style="color: #ef4444;">*</span></label>
                        <input type="file" name="product_image" id="product_image" class="file-input" accept="image/jpeg,image/png,image/webp" required>
                        <label for="product_image" class="file-input-label" id="fileLabel">
                            <i class="fas fa-upload" style="margin-right: 0.5rem;"></i>
                            <span>Choose an image (JPG, PNG, WebP)</span>
                        </label>
                        <div id="imageError" class="validation-error"></div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <button type="button" class="btn" style="background-color: #f3f4f6; color: #374151;" onclick="window.history.back();">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Save Product
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Toggle sidebar
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        let isCollapsed = false;
        
        toggleSidebar.addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('expanded');
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
        
        // Live validation for product name
        const productName = document.getElementById('name');
        productName.addEventListener('input', validateProductName);
        
        function validateProductName() {
            const nameValue = productName.value.trim();
            const errorElement = document.getElementById('nameError');
            
            if (!nameValue) {
                errorElement.textContent = 'Product name is required';
                return false;
            }
            
            // Check minimum length
            if (nameValue.length < 3) {
                errorElement.textContent = 'Product name must be at least 3 characters long';
                return false;
            }
            
            // Allow alphabets, numbers, and spaces
            if (!/^[a-zA-Z0-9\s]+$/.test(nameValue)) {
                errorElement.textContent = 'Product name can only contain letters, numbers, and spaces';
                return false;
            }
            
            errorElement.textContent = '';
            return true;
        }
        
        // Live validation for description
        const description = document.getElementById('description');
        description.addEventListener('input', validateDescription);
        
        function validateDescription() {
            const descValue = description.value.trim();
            const errorElement = document.getElementById('descriptionError');
            
            if (descValue) {
                // Check minimum word count
                const wordCount = descValue.trim().split(/\s+/).filter(word => word.length > 0).length;
                if (wordCount < 7) {
                    errorElement.textContent = 'Description must be at least 7 words long';
                    return false;
                }
                
                // Validate characters
                if (!/^[a-zA-Z0-9\s\.,!?@#\$%\^&\*()_+\-=\[\]{};':"\\|,.<>\/?~`]+$/.test(descValue)) {
                    errorElement.textContent = 'Description contains invalid characters';
                    return false;
                }
            }
            
            errorElement.textContent = '';
            return true;
        }
        
        // Live validation for price
        const price = document.getElementById('price');
        price.addEventListener('input', validatePrice);
        
        function validatePrice() {
            const priceValue = parseFloat(price.value);
            const errorElement = document.getElementById('priceError');
            
            if (isNaN(priceValue) || priceValue <= 0) {
                errorElement.textContent = 'Price must be greater than 0';
                return false;
            }
            
            errorElement.textContent = '';
            return true;
        }
        
        // Live validation for stock
        const stock = document.getElementById('stock');
        stock.addEventListener('input', validateStock);
        
        function validateStock() {
            const stockValue = parseInt(stock.value);
            const errorElement = document.getElementById('stockError');
            
            if (isNaN(stockValue) || stockValue < 0) {
                errorElement.textContent = 'Stock must be 0 or greater';
                return false;
            }
            
            errorElement.textContent = '';
            return true;
        }
        
        // File input handling and validation
        const productImage = document.getElementById('product_image');
        const fileLabel = document.getElementById('fileLabel');
        
        productImage.addEventListener('change', function() {
            const file = this.files[0];
            const errorElement = document.getElementById('imageError');
            
            if (file) {
                // Update the label text to show the file name
                fileLabel.querySelector('span').textContent = file.name;
                
                // Validate the file type
                const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    errorElement.textContent = 'Invalid file type. Please upload a JPG, PNG, or WebP image.';
                    return false;
                }
                
                errorElement.textContent = '';
                return true;
            } else {
                fileLabel.querySelector('span').textContent = 'Choose an image (JPG, PNG, WebP)';
                errorElement.textContent = 'Please select an image';
                return false;
            }
        });
        
        function validateImage() {
            const file = productImage.files[0];
            const errorElement = document.getElementById('imageError');
            
            if (!file) {
                errorElement.textContent = 'Please select an image';
                return false;
            }
            
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                errorElement.textContent = 'Invalid file type. Please upload a JPG, PNG, or WebP image.';
                return false;
            }
            
            errorElement.textContent = '';
            return true;
        }
        
        // Form submission validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            // Trigger all validations
            const isNameValid = validateProductName();
            const isDescriptionValid = validateDescription();
            const isPriceValid = validatePrice();
            const isStockValid = validateStock();
            const isImageValid = validateImage();
            
            // Validate category
            const categoryId = document.getElementById('category_id');
            const categoryError = document.getElementById('categoryError');
            let isCategoryValid = true;
            
            if (!categoryId.value) {
                categoryError.textContent = 'Category is required';
                isCategoryValid = false;
            } else {
                categoryError.textContent = '';
            }
            
            // Prevent form submission if any validation fails
            if (!isNameValid || !isDescriptionValid || !isPriceValid || !isStockValid || !isImageValid || !isCategoryValid) {
                e.preventDefault();
                // Scroll to the first error
                const firstError = document.querySelector('.validation-error:not(:empty)');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        // Add input validation on blur for better UX
        document.querySelectorAll('input, textarea, select').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.id === 'name') validateProductName();
                else if (this.id === 'description') validateDescription();
                else if (this.id === 'price') validatePrice();
                else if (this.id === 'stock') validateStock();
                else if (this.id === 'category_id') {
                    const categoryId = document.getElementById('category_id');
                    const categoryError = document.getElementById('categoryError');
                    if (!categoryId.value) {
                        categoryError.textContent = 'Category is required';
                    } else {
                        categoryError.textContent = '';
                    }
                }
            });
        });
    </script>
</body>
</html>