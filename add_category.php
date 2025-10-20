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
$uploadDir = 'uploads/categories/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $imagePath = '';
    
    // Validate input
    if (empty($name)) {
        $message = 'Category name is required';
        $message_type = 'error';
    } else {
        // Check if category already exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Category already exists';
            $message_type = 'error';
        } else {
            // Handle file upload
            if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
                $fileExtension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('category_') . '.' . $fileExtension;
                $targetPath = $uploadDir . $fileName;
                
                // Check if image file is a valid image
                $check = getimagesize($_FILES['category_image']['tmp_name']);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES['category_image']['tmp_name'], $targetPath)) {
                        $imagePath = $targetPath;
                    } else {
                        $message = 'Error uploading image';
                        $message_type = 'error';
                        return;
                    }
                } else {
                    $message = 'File is not a valid image';
                    $message_type = 'error';
                    return;
                }
            }
            
            // Insert new category
            $stmt = $conn->prepare("INSERT INTO categories (name, description, image) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $description, $imagePath);
            
            if ($stmt->execute()) {
                $message = 'Category added successfully';
                $message_type = 'success';
                // Clear form
                $_POST = array();
            } else {
                $message = 'Error adding category: ' . $conn->error;
                $message_type = 'error';
            }
        }
        $stmt->close();
    }
}

// Fetch all categories
$categories = [];
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
if ($result) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - NexusGear</title>
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
        
        /* Custom styles for the form */
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
            line-height: 1.5;
            color: #111827;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-input:focus {
            border-color: #4f46e5;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 500;
            line-height: 1.5;
            text-align: center;
            text-decoration: none;
            white-space: nowrap;
            border: 1px solid transparent;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        
        .btn-primary {
            color: white;
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }
        
        .btn-outline {
            color: #4f46e5;
            background-color: transparent;
            border-color: #4f46e5;
        }
        
        .btn-outline:hover {
            background-color: #eef2ff;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 0.375rem;
        }
        
        .alert-success {
            color: #065f46;
            background-color: #d1fae5;
            border-color: #a7f3d0;
        }
        
        .alert-error {
            color: #991b1b;
            background-color: #fee2e2;
            border-color: #fecaca;
        }
        
        .table-container {
            margin-top: 2rem;
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table th {
            font-weight: 600;
            color: #374151;
            background-color: #f9fafb;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            border-radius: 9999px;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
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
                <a href="add_product.php" class="nav-link">
                    <i class="fas fa-box"></i>
                    <span class="nav-text">Add Product</span>
                    <span class="tooltip">Add Product</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_product.php" class="nav-link">
                    <i class="fas fa-cogs"></i>
                    <span class="nav-text">Manage Products</span>
                    <span class="tooltip">Manage Products</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="add_category.php" class="nav-link active">
                    <i class="fas fa-tags"></i>
                    <span class="nav-text">Categories</span>
                    <span class="tooltip">Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_order_manage.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="nav-text">Orders</span>
                    <span class="notification-badge">5</span>
                    <span class="tooltip">Orders</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <header style="background-color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h1 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">Manage Categories</h1>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem;">
                    
                    <div style="position: relative;">
                        <button id="userMenuButton" style="display: flex; align-items: center; gap: 0.5rem; border: none; background: transparent; cursor: pointer;">
                            <div style="width: 2rem; height: 2rem; border-radius: 9999px; background-color: #eef2ff; display: flex; align-items: center; justify-content: center; color: #4f46e5; font-weight: 500;">
                                <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                            </div>
                            <span style="font-size: 0.875rem; font-weight: 500; color: #374151;"><?php echo htmlspecialchars($admin_username); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        <div id="userMenu" style="display: none; position: absolute; right: 0; margin-top: 0.5rem; width: 12rem; background-color: white; border-radius: 0.375rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 0.25rem 0; z-index: 50; border: 1px solid #e5e7eb;">
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
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h2 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin-bottom: 1.5rem;">Add New Category</h2>
                
                <form method="POST" action="" enctype="multipart/form-data" id="categoryForm" novalidate>
                    <div class="form-group">
                        <label for="name" class="form-label">Category Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               pattern="^[a-zA-Z\s]+" 
                               oninput="validateCategoryName(this)"
                               required>
                        <div id="nameError" class="validation-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-input" rows="3" 
                                 oninput="validateDescription(this)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div id="descriptionError" class="validation-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_image" class="form-label">Category Image</label>
                        <input type="file" id="category_image" name="category_image" class="form-input" 
                               accept="image/jpeg, image/png, image/jpg, image/webp"
                               onchange="validateImage(this)">
                        <p class="text-sm text-gray-500 mt-1">Upload an image for this category (JPEG, PNG, JPG, WEBP)</p>
                        <div id="imageError" class="validation-error"></div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Save Category
                        </button>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-undo" style="margin-right: 0.5rem;"></i> Reset
                        </button>
                    </div>
                </form>
                

            </div>
        </main>
    </div>

    <style>
        .validation-error {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .is-invalid {
            border-color: #ef4444 !important;
        }
        
        .is-valid {
            border-color: #10b981 !important;
        }
    </style>
    
    <script>
        // Form validation functions
        function validateCategoryName(input) {
            const nameError = document.getElementById('nameError');
            const regex = /^[a-zA-Z\s]*$/;
            
            if (!regex.test(input.value)) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                nameError.textContent = 'Only alphabets and spaces are allowed';
                nameError.style.display = 'block';
                return false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                nameError.style.display = 'none';
                return true;
            }
        }
        
        function validateDescription(textarea) {
            const descError = document.getElementById('descriptionError');
            // Allow letters, numbers, spaces, and basic punctuation
            const regex = /^[a-zA-Z0-9\s.,!?()\[\]{}:;"'\-+=\/*&^%$#@!~`_|]*$/;
            
            if (!regex.test(textarea.value)) {
                textarea.classList.add('is-invalid');
                textarea.classList.remove('is-valid');
                descError.textContent = 'Special characters are not allowed';
                descError.style.display = 'block';
                return false;
            } else {
                textarea.classList.remove('is-invalid');
                textarea.classList.add('is-valid');
                descError.style.display = 'none';
                return true;
            }
        }
        
        function validateImage(input) {
            const imageError = document.getElementById('imageError');
            const file = input.files[0];
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            
            if (file) {
                if (!validTypes.includes(file.type)) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    imageError.textContent = 'Only JPG, JPEG, PNG, and WEBP images are allowed';
                    imageError.style.display = 'block';
                    input.value = ''; // Clear the file input
                    return false;
                } else if (file.size > 2 * 1024 * 1024) { // 2MB limit
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    imageError.textContent = 'Image size should be less than 2MB';
                    imageError.style.display = 'block';
                    input.value = ''; // Clear the file input
                    return false;
                } else {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                    imageError.style.display = 'none';
                    return true;
                }
            }
            return true;
        }
        
        // Form submission validation
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            const nameValid = validateCategoryName(document.getElementById('name'));
            const descValid = validateDescription(document.getElementById('description'));
            const fileInput = document.getElementById('category_image');
            let imageValid = true;
            
            if (fileInput.files.length > 0) {
                imageValid = validateImage(fileInput);
            }
            
            if (!nameValid || !descValid || !imageValid) {
                e.preventDefault();
                return false;
            }
        });
        
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