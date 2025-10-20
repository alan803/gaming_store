<?php
// Enable error reporting for debugging (log to file, not display for AJAX)
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

session_start();
include 'connection.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    if (isset($_GET['action']) && $_GET['action'] === 'save_address') {
        header('Content-Type: application/json');
        ini_set('display_errors', 0);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }
    die("Database connection failed. Please try again later.");
}

// Check if user is logged in
if (!isset($_SESSION['auth_user_id'])) {
    error_log("Unauthorized access attempt: No user_id in session");
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['auth_user_id'];

// Verify user_id exists in users table
$check_user_query = "SELECT user_id FROM users WHERE user_id = ?";
$stmt = $conn->prepare($check_user_query);
if (!$stmt) {
    error_log("Prepare failed for check_user_query: " . $conn->error);
    die("Internal server error");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    error_log("Invalid user_id: $user_id not found in users table");
    die("Invalid user ID. Please log in again.");
}
$stmt->close();

// Handle different actions based on query parameters or form submissions
$action = $_GET['action'] ?? '';

if ($action === 'get_address') {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    $address_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$address_id) {
        error_log("Invalid address ID in get_address: " . ($_GET['id'] ?? 'null'));
        echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
        exit();
    }

    try {
        $query = "SELECT * FROM user_address WHERE address_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $address_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $address = $result->fetch_assoc();
            echo json_encode(['success' => true, 'address' => $address]);
        } else {
            error_log("Address not found for address_id: $address_id, user_id: $user_id");
            echo json_encode(['success' => false, 'message' => 'Address not found']);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error in get_address: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

if ($action === 'delete_address' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);

    if (!$address_id) {
        error_log("Invalid address ID in delete_address: " . ($_POST['address_id'] ?? 'null'));
        echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
        exit();
    }

    try {
        // Check if address is default
        $check_query = "SELECT is_default FROM user_address WHERE address_id = ? AND user_id = ?";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $address_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $address = $result->fetch_assoc();
        $stmt->close();

        if (!$address) {
            error_log("Address not found for deletion: address_id=$address_id, user_id=$user_id");
            echo json_encode(['success' => false, 'message' => 'Address not found']);
            exit();
        }

        if ($address['is_default']) {
            error_log("Attempt to delete default address: address_id=$address_id");
            echo json_encode(['success' => false, 'message' => 'Cannot delete default address']);
            exit();
        }

        $delete_query = "DELETE FROM user_address WHERE address_id = ? AND user_id = ?";
        $stmt = $conn->prepare($delete_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $address_id, $user_id);

        if ($stmt->execute()) {
            error_log("Address deleted successfully: address_id=$address_id, user_id=$user_id");
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error in delete_address: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting address: ' . $e->getMessage()]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
    header('Content-Type: application/json');
    ini_set('display_errors', 0);

    try {
        $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
        $address_line1 = filter_input(INPUT_POST, 'address_line1', FILTER_SANITIZE_STRING);
        $address_line2 = filter_input(INPUT_POST, 'address_line2', FILTER_SANITIZE_STRING) ?? '';
        $landmark = filter_input(INPUT_POST, 'landmark', FILTER_SANITIZE_STRING) ?? '';
        $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
        $state = filter_input(INPUT_POST, 'state', FILTER_SANITIZE_STRING);
        $postal_code = filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_STRING);
        $address_type = filter_input(INPUT_POST, 'address_type', FILTER_SANITIZE_STRING);
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        // Log received data
        error_log("Received save_address data: address_id=" . ($address_id ?: 'null') . ", full_name=$full_name, phone_number=$phone_number, address_line1=$address_line1, address_line2=$address_line2, landmark=$landmark, city=$city, state=$state, postal_code=$postal_code, address_type=$address_type, is_default=$is_default");

        // Server-side validation
        if (empty($full_name) || !preg_match("/^[A-Za-z\s]{2,50}$/", $full_name)) {
            throw new Exception("Full name must be 2-50 characters, letters and spaces only.");
        }

        if (empty($phone_number) || !preg_match("/^[6-9]\d{9}$/", $phone_number)) {
            throw new Exception("Phone number must be a 10-digit number starting with 6-9.");
        }

        if (empty($address_line1) || !preg_match("/^.{2,100}$/", $address_line1)) {
            throw new Exception("Address Line 1 must be 2-100 characters.");
        }

        if ($address_line2 && !preg_match("/^.{0,100}$/", $address_line2)) {
            throw new Exception("Address Line 2 must be 0-100 characters.");
        }

        if ($landmark && !preg_match("/^.{0,100}$/", $landmark)) {
            throw new Exception("Landmark must be 0-100 characters.");
        }

        if (empty($city) || !preg_match("/^[A-Za-z\s]{2,50}$/", $city)) {
            throw new Exception("City must be 2-50 characters, letters and spaces only.");
        }

        if (empty($state) || !preg_match("/^[A-Za-z\s]{2,50}$/", $state)) {
            throw new Exception("State must be 2-50 characters, letters and spaces only.");
        }

        if (empty($postal_code) || !preg_match("/^\d{6}$/", $postal_code)) {
            throw new Exception("Postal code must be a 6-digit number.");
        }

        if (empty($address_type) || !in_array($address_type, ['home', 'work', 'other'])) {
            throw new Exception("Invalid address type.");
        }

        // Handle default address
        if ($is_default) {
            $reset_query = "UPDATE user_address SET is_default = 0 WHERE user_id = ?";
            $stmt = $conn->prepare($reset_query);
            if (!$stmt) {
                throw new Exception("Prepare failed for reset default: " . $conn->error);
            }
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for reset default: " . $stmt->error);
            }
            $stmt->close();
        }

        $current_time = date('Y-m-d H:i:s');

        if ($address_id) {
            // Update existing address
            $update_query = "UPDATE user_address SET full_name = ?, phone_number = ?, address_line1 = ?, address_line2 = ?, landmark = ?, city = ?, state = ?, postal_code = ?, address_type = ?, is_default = ?, updated_at = ? WHERE address_id = ? AND user_id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                throw new Exception("Prepare failed for update: " . $conn->error);
            }
            $stmt->bind_param("sssssssssisi", $full_name, $phone_number, $address_line1, $address_line2, $landmark, $city, $state, $postal_code, $address_type, $is_default, $current_time, $address_id, $user_id);
            error_log("Executing update query for address_id=$address_id");
        } else {
            // Insert new address
            $insert_query = "INSERT INTO user_address (user_id, full_name, phone_number, address_line1, address_line2, landmark, city, state, postal_code, address_type, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            if (!$stmt) {
                throw new Exception("Prepare failed for insert: " . $conn->error);
            }
            $stmt->bind_param("isssssssssiss", $user_id, $full_name, $phone_number, $address_line1, $address_line2, $landmark, $city, $state, $postal_code, $address_type, $is_default, $current_time, $current_time);
            error_log("Executing insert query for user_id=$user_id");
        }

        if ($stmt->execute()) {
            error_log("Address saved successfully: address_id=" . ($address_id ?: $conn->insert_id));
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error in save_address: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error saving address: ' . $e->getMessage()]);
    }
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
    $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_STRING);

    // Validate inputs
    if (empty($full_name)) {
        $error_message = "Full name is required.";
    } elseif (!preg_match("/^[A-Za-z\s]+$/", $full_name)) {
        $error_message = "Full name should contain only letters and spaces.";
    } elseif ($phone_number && !preg_match("/^[6-9]\d{9}$/", $phone_number)) {
        $error_message = "Invalid phone number. It should be a 10-digit number starting with 6-9.";
    } else {
        // Fetch current profile data for comparison
        $check_query = "SELECT full_name, phone_number, dob, profile_image FROM user_profile WHERE user_id = ?";
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            error_log("Prepare failed for profile check: " . $conn->error);
            $error_message = "Internal server error";
        } else {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_profile = $result->num_rows > 0 ? $result->fetch_assoc() : null;
            $stmt->close();

            // Initialize variables
            $fields_to_update = [];
            $params = [];
            $types = "";
            $profile_image = $current_profile['profile_image'] ?? '';

            // Check which fields have changed
            if ($current_profile) {
                // Update existing profile
                if ($full_name !== $current_profile['full_name']) {
                    $fields_to_update[] = "full_name = ?";
                    $params[] = $full_name;
                    $types .= "s";
                }
                if ($phone_number !== ($current_profile['phone_number'] ?? '') && $phone_number !== '') {
                    $fields_to_update[] = "phone_number = ?";
                    $params[] = $phone_number;
                    $types .= "s";
                }
                if ($dob !== ($current_profile['dob'] ?? '') && $dob !== '') {
                    $fields_to_update[] = "dob = ?";
                    $params[] = $dob;
                    $types .= "s";
                }
            } else {
                // Insert new profile, include all provided fields
                $fields_to_update[] = "full_name = ?";
                $params[] = $full_name;
                $types .= "s";
                if ($phone_number) {
                    $fields_to_update[] = "phone_number = ?";
                    $params[] = $phone_number;
                    $types .= "s";
                }
                if ($dob) {
                    $fields_to_update[] = "dob = ?";
                    $params[] = $dob;
                    $types .= "s";
                }
            }

            // Handle file upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/';
                $file_name = time() . '_' . basename($_FILES['profile_image']['name']);
                $target_file = $upload_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
                if (!in_array($imageFileType, $allowed_types)) {
                    $error_message = "Invalid file type. Only JPG, JPEG, PNG, WEBP, and AVIF are allowed.";
                } elseif ($_FILES['profile_image']['size'] > 5000000) {
                    $error_message = "File is too large. Maximum size is 5MB.";
                } else {
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                        $profile_image = $target_file;
                        $fields_to_update[] = "profile_image = ?";
                        $params[] = $profile_image;
                        $types .= "s";
                    } else {
                        $error_message = "Error uploading file.";
                    }
                }
            }

            if (!isset($error_message) && (!empty($fields_to_update) || !$current_profile)) {
                if ($current_profile) {
                    // Update only changed fields
                    if (!empty($fields_to_update)) {
                        $update_query = "UPDATE user_profile SET " . implode(", ", $fields_to_update) . " WHERE user_id = ?";
                        $params[] = $user_id;
                        $types .= "i";
                        $stmt = $conn->prepare($update_query);
                        if (!$stmt) {
                            error_log("Prepare failed for profile update: " . $conn->error);
                            $error_message = "Internal server error";
                        } else {
                            $stmt->bind_param($types, ...$params);
                        }
                    } else {
                        // No fields changed, skip update
                        $success_message = "No changes detected.";
                    }
                } else {
                    // Insert new profile
                    $insert_query = "INSERT INTO user_profile (user_id, " . implode(", ", array_map(function($field) {
                        return str_replace(" = ?", "", $field);
                    }, $fields_to_update)) . ") VALUES (?" . str_repeat(", ?", count($fields_to_update)) . ")";
                    $params = array_merge([$user_id], $params);
                    $types = "i" . $types;
                    $stmt = $conn->prepare($insert_query);
                    if (!$stmt) {
                        error_log("Prepare failed for profile insert: " . $conn->error);
                        $error_message = "Internal server error";
                    } else {
                        $stmt->bind_param($types, ...$params);
                    }
                }

                if (!isset($error_message) && isset($stmt) && $stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                    if ($full_name !== ($current_profile['full_name'] ?? $_SESSION['auth_username'])) {
                        $_SESSION['auth_username'] = $full_name; // Update session username only if full_name changed
                    }
                    $stmt->close();
                } elseif (!isset($error_message)) {
                    error_log("Error updating profile: " . $stmt->error);
                    $error_message = "Error updating profile: " . $stmt->error;
                    $stmt->close();
                }
            } elseif (!isset($error_message) && empty($fields_to_update)) {
                $success_message = "No changes detected.";
            }
        }
    }
}

// Fetch user profile
$profile_query = "SELECT * FROM user_profile WHERE user_id = ?";
$stmt = $conn->prepare($profile_query);
if (!$stmt) {
    error_log("Prepare failed for profile fetch: " . $conn->error);
    die("Internal server error");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
if (!$profile_result) {
    error_log("Error fetching profile: " . $conn->error);
    die("Error fetching profile: " . $conn->error);
}
$user_profile = $profile_result->fetch_assoc();
$stmt->close();

// Fetch all addresses
$address_query = "SELECT * FROM user_address WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($address_query);
if (!$stmt) {
    error_log("Prepare failed for address fetch: " . $conn->error);
    die("Internal server error");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$address_result = $stmt->get_result();
if (!$address_result) {
    error_log("Error fetching addresses: " . $conn->error);
    die("Error fetching addresses: " . $conn->error);
}
$addresses = [];
while ($row = $address_result->fetch_assoc()) {
    $addresses[] = $row;
}
$stmt->close();

// Calculate cart count
$cart_count = 0;
$count_query = "SELECT COUNT(*) as total_items FROM cart WHERE user_id = ?";
$stmt = $conn->prepare($count_query);
if (!$stmt) {
    error_log("Prepare failed for cart count: " . $conn->error);
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $cart_count = $count_result->fetch_assoc()['total_items'] ?? 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
            scroll-behavior: smooth;
            font-family: 'Poppins', sans-serif;
        }
        
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background-color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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

        .dropdown-menu {
            transition: all 0.2s ease;
            transform-origin: top right;
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

        .gradient-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 15px 30px -5px rgba(79, 70, 229, 0.15);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-light));
            color: white;
            font-weight: 500;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-block;
            text-align: center;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px -5px rgba(79, 70, 229, 0.4);
        }

        .btn-secondary {
            border: 2px solid var(--primary);
            color: var(--primary);
            font-weight: 500;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: white;
            cursor: pointer;
            display: inline-block;
            text-align: center;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background-color: var(--primary);
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        section:first-of-type {
            margin-top: 50px;
        }

        .mobile-menu {
            transition: transform 0.3s ease-in-out;
        }

        input, select, textarea {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
        }

        .invalid {
            border-color: #ef4444;
        }

        .error-text {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .validation-rules {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .validation-rules ul {
            list-style-type: disc;
            padding-left: 1.5rem;
        }

        .validation-rules li {
            font-size: 0.875rem;
            color: #4b5563;
            margin-bottom: 0.5rem;
        }

        .debug-message {
            background-color: #fefcbf;
            border: 1px solid #f6e05e;
            color: #744210;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Debug Information -->
    <?php if (isset($error_message)): ?>
        <div class="container mx-auto px-4 debug-message">
            <strong>Debug Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white shadow-sm">
        <div class="container mx-auto px-4 py-3" style="max-width: 1280px;">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-gamepad text-white"></i>
                    </div>
                    <a href="homepage.php" class="text-2xl font-bold text-gray-800">Nexus<span class="text-indigo-600">Gear</span></a>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="homepage.php" class="nav-link">Home</a>
                    <a href="products.php" class="nav-link">Shop</a>
                    <a href="homepage.php#about" class="nav-link">About</a>
                </div>
                
                <div class="flex items-center space-x-6">
                    <a href="cart.php" class="relative text-gray-600 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge absolute -top-2 -right-2 bg-indigo-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <div class="relative group">
                        <button id="profileDropdownButton" class="flex items-center space-x-2 text-gray-700 hover:text-indigo-600 transition-colors" aria-expanded="false">
                            <span class="hidden md:inline font-medium"><?php echo htmlspecialchars($_SESSION['auth_username']); ?></span>
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                        </button>
                        <div id="profileDropdown" class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50 hidden border border-gray-100">
                            <a href="user_profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-user-circle mr-3 text-gray-400 w-5"></i>Profile
                            </a>
                            <a href="orders.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-shopping-bag mr-3 text-gray-400 w-5"></i>My Orders
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form action="logout.php" method="post" class="w-full">
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center">
                                    <i class="fas fa-sign-out-alt mr-3 text-red-400 w-5"></i>Logout
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <button id="mobileMenuButton" class="md:hidden text-gray-600 hover:text-indigo-600">
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
                    <a href="homepage.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium border-b border-gray-100">Home</a>
                    <a href="products.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium border-b border-gray-100">Shop</a>
                    <a href="user_profile.php" class="block py-3 text-indigo-600 font-medium border-b border-gray-100">Profile</a>
                    <form action="logout.php" method="post" class="w-full mt-6">
                        <button type="submit" class="w-full text-left py-3 text-red-600 hover:text-red-700 font-medium">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <section class="gradient-bg py-12">
        <div class="container mx-auto px-4" style="max-width: 1280px;">
            <div class="flex items-center space-x-3 mb-8">
                <i class="fas fa-arrow-left cursor-pointer text-indigo-600 hover:text-indigo-700" onclick="history.back()"></i>
                <h1 class="text-4xl font-bold">My Profile</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Profile Information -->
                <div class="lg:col-span-1">
                    <div class="card p-6">
                        <div class="text-center mb-6">
                            <div class="w-24 h-24 rounded-full bg-indigo-100 flex items-center justify-center mx-auto mb-4 text-4xl text-indigo-600 overflow-hidden">
                                <?php if ($user_profile && !empty($user_profile['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($user_profile['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($user_profile['full_name'] ?? $_SESSION['auth_username']); ?></h2>
                            <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($user_profile['phone_number'] ?? 'No phone'); ?></p>
                            <?php if ($user_profile && !empty($user_profile['dob'])): ?>
                                <p class="text-gray-500 text-xs mt-2">DOB: <?php echo htmlspecialchars($user_profile['dob']); ?></p>
                            <?php endif; ?>
                        </div>
                        <button onclick="openEditProfileModal()" class="w-full btn-primary mb-3">
                            <i class="fas fa-edit mr-2"></i> Edit Profile
                        </button>
                        <a href="orders.php" class="w-full btn-secondary block text-center">
                            <i class="fas fa-shopping-bag mr-2"></i> View Orders
                        </a>
                    </div>
                </div>

                <!-- Addresses Section -->
                <div class="lg:col-span-2">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">Delivery Addresses</h2>
                        <button onclick="openAddressModal()" class="btn-primary">
                            <i class="fas fa-plus mr-2"></i> Add Address
                        </button>
                    </div>

                    <?php if (count($addresses) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($addresses as $address): ?>
                                <div class="card p-6 relative">
                                    <?php if ($address['is_default']): ?>
                                        <div class="absolute top-3 right-3 bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                                            Default
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-4">
                                        <h3 class="font-bold text-lg text-gray-900 capitalize"><?php echo htmlspecialchars($address['address_type']); ?></h3>
                                        <p class="text-gray-600 font-semibold"><?php echo htmlspecialchars($address['full_name']); ?></p>
                                        <p class="text-gray-600"><?php echo htmlspecialchars($address['phone_number']); ?></p>
                                    </div>

                                    <div class="text-sm text-gray-600 mb-4 space-y-1">
                                        <p><?php echo htmlspecialchars($address['address_line1']); ?></p>
                                        <?php if (!empty($address['address_line2'])): ?>
                                            <p><?php echo htmlspecialchars($address['address_line2']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($address['landmark'])): ?>
                                            <p class="text-gray-500">Near: <?php echo htmlspecialchars($address['landmark']); ?></p>
                                        <?php endif; ?>
                                        <p><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?> <?php echo htmlspecialchars($address['postal_code']); ?></p>
                                    </div>

                                    <div class="flex space-x-2 pt-4 border-t border-gray-200">
                                        <button onclick="editAddress(<?php echo $address['address_id']; ?>)" class="flex-1 btn-secondary text-sm">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </button>
                                        <?php if (!$address['is_default']): ?>
                                            <button onclick="deleteAddress(<?php echo $address['address_id']; ?>)" class="flex-1 bg-red-100 text-red-600 hover:bg-red-200 font-medium px-3 py-2 rounded-lg text-sm transition-colors border-none cursor-pointer">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </button>
                                        <?php else: ?>
                                            <button disabled class="flex-1 bg-gray-100 text-gray-400 font-medium px-3 py-2 rounded-lg text-sm border-none cursor-not-allowed">
                                                <i class="fas fa-lock mr-1"></i> Default
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="card p-12 text-center">
                            <i class="fas fa-map-marker-alt text-5xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 text-lg mb-6">No addresses added yet</p>
                            <button onclick="openAddressModal()" class="btn-primary">
                                <i class="fas fa-plus mr-2"></i> Add Your First Address
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Edit Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Edit Profile</h2>
                <button onclick="closeEditProfileModal()" class="text-gray-500 hover:text-gray-700 text-2xl bg-none border-none cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="profileForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="update_profile" value="1">
                <div class="flex justify-center mb-6">
                    <div class="relative">
                        <div id="profileImagePreview" class="w-32 h-32 rounded-full bg-indigo-100 flex items-center justify-center text-4xl text-indigo-600 overflow-hidden">
                            <?php if ($user_profile && !empty($user_profile['profile_image'])): ?>
                                <img id="imagePreview" src="<?php echo htmlspecialchars($user_profile['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-user" id="defaultIcon"></i>
                            <?php endif; ?>
                        </div>
                        <label for="profile_image" class="absolute bottom-0 right-0 bg-indigo-600 text-white rounded-full p-2 cursor-pointer hover:bg-indigo-700 transition">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp,.avif" class="hidden" onchange="previewImage(event)">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_profile['full_name'] ?? ''); ?>" pattern="[A-Za-z\s]+" title="Name should contain only letters and spaces" minlength="2" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_profile['phone_number'] ?? ''); ?>" pattern="[6-9]\d{9}" title="Please enter a valid 10-digit phone number starting with 6-9" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                    <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($user_profile['dob'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <?php if (isset($success_message)): ?>
                    <div id="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div id="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="flex space-x-3 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeEditProfileModal()" class="flex-1 btn-secondary">Cancel</button>
                    <button type="submit" class="flex-1 btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit Address Modal -->
    <div id="addressModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold">Add New Address</h2>
                <button onclick="closeAddressModal()" class="text-gray-500 hover:text-gray-700 text-2xl bg-none border-none cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="validation-rules">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Input Requirements:</h3>
                <ul>
                    <li>Full Name: 2-50 characters, letters and spaces only, required.</li>
                    <li>Phone Number: 10-digit number starting with 6-9, required.</li>
                    <li>Address Line 1: 2-100 characters, required.</li>
                    <li>Address Line 2: 0-100 characters, optional.</li>
                    <li>Landmark: 0-100 characters, optional.</li>
                    <li>City: 2-50 characters, letters and spaces only, required.</li>
                    <li>State: 2-50 characters, letters and spaces only, required.</li>
                    <li>Postal Code: 6-digit number, required.</li>
                    <li>Address Type: Must select Home, Work, or Other, required.</li>
                </ul>
            </div>

            <form id="addressForm" onsubmit="submitAddress(event)" class="space-y-4">
                <input type="hidden" id="address_id" name="address_id" value="">
                <input type="hidden" name="save_address" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" id="address_full_name" name="full_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <div id="address_full_name_error" class="error-text hidden"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                        <input type="tel" id="address_phone_number" name="phone_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <div id="address_phone_number_error" class="error-text hidden"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 1 *</label>
                    <input type="text" id="address_line1" name="address_line1" placeholder="House No., Building Name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <div id="address_line1_error" class="error-text hidden"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2" placeholder="Road Name, Area, Colony" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <div id="address_line2_error" class="error-text hidden"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Landmark</label>
                    <input type="text" id="landmark" name="landmark" placeholder="E.g., near park, near temple" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <div id="landmark_error" class="error-text hidden"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                        <input type="text" id="city" name="city" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <div id="city_error" class="error-text hidden"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">State *</label>
                        <input type="text" id="state" name="state" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <div id="state_error" class="error-text hidden"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code *</label>
                        <input type="text" id="postal_code" name="postal_code" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <div id="postal_code_error" class="error-text hidden"></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address Type *</label>
                        <select id="address_type" name="address_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Type</option>
                            <option value="home">Home</option>
                            <option value="work">Work</option>
                            <option value="other">Other</option>
                        </select>
                        <div id="address_type_error" class="error-text hidden"></div>
                    </div>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="is_default" name="is_default" class="w-4 h-4 text-indigo-600 rounded">
                    <label for="is_default" class="ml-2 text-sm text-gray-700">Set as default address</label>
                </div>

                <div id="addressSuccessMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"></div>
                <div id="addressErrorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>

                <div class="flex space-x-3 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeAddressModal()" class="flex-1 btn-secondary">Cancel</button>
                    <button type="submit" id="saveAddressBtn" class="flex-1 btn-primary">Save Address</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="container mx-auto px-4 py-12" style="max-width: 1280px;">
            <div class="text-center">
                <p>&copy; 2025 NexusGear. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Profile dropdown
        const profileDropdownButton = document.getElementById('profileDropdownButton');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdownButton && profileDropdown) {
            profileDropdownButton.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!profileDropdownButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });
        }

        // Mobile menu
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMobileMenu = document.getElementById('closeMobileMenu');

        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });

            if (closeMobileMenu) {
                closeMobileMenu.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                });
            }
        }

        // Profile functions
        function openEditProfileModal() {
            document.getElementById('profileModal').classList.add('show');
            document.getElementById('successMessage')?.classList.add('hidden');
            document.getElementById('errorMessage')?.classList.add('hidden');
        }

        function closeEditProfileModal() {
            document.getElementById('profileModal').classList.remove('show');
            document.getElementById('successMessage')?.classList.add('hidden');
            document.getElementById('errorMessage')?.classList.add('hidden');
        }

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profileImagePreview');
                    const defaultIcon = document.getElementById('defaultIcon');
                    
                    if (defaultIcon) {
                        defaultIcon.remove();
                    }
                    
                    preview.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'w-full h-full object-cover';
                    img.id = 'imagePreview';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        }

        // Address functions
        function openAddressModal() {
            document.getElementById('addressModal').classList.add('show');
            document.getElementById('addressForm').reset();
            document.getElementById('address_id').value = '';
            document.getElementById('modalTitle').textContent = 'Add New Address';
            clearAddressErrors();
        }

        function closeAddressModal() {
            document.getElementById('addressModal').classList.remove('show');
            document.getElementById('addressSuccessMessage').classList.add('hidden');
            document.getElementById('addressErrorMessage').classList.add('hidden');
            clearAddressErrors();
        }

        function editAddress(addressId) {
            console.log('Fetching address for ID:', addressId);
            fetch('?action=get_address&id=' + addressId)
            .then(response => {
                console.log('Get address response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Get address response data:', data);
                if (data.success) {
                    const addr = data.address;
                    document.getElementById('address_id').value = addr.address_id;
                    document.getElementById('address_full_name').value = addr.full_name;
                    document.getElementById('address_phone_number').value = addr.phone_number;
                    document.getElementById('address_line1').value = addr.address_line1;
                    document.getElementById('address_line2').value = addr.address_line2 || '';
                    document.getElementById('landmark').value = addr.landmark || '';
                    document.getElementById('city').value = addr.city;
                    document.getElementById('state').value = addr.state;
                    document.getElementById('postal_code').value = addr.postal_code;
                    document.getElementById('address_type').value = addr.address_type;
                    document.getElementById('is_default').checked = addr.is_default == 1;
                    document.getElementById('modalTitle').textContent = 'Edit Address';
                    document.getElementById('addressModal').classList.add('show');
                    validateAddressForm();
                } else {
                    console.error('Error fetching address:', data.message);
                    alert(data.message || 'Error fetching address');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error fetching address: ' + error.message);
            });
        }

        function deleteAddress(addressId) {
            if (confirm('Are you sure you want to delete this address?')) {
                console.log('Deleting address ID:', addressId);
                fetch('?action=delete_address', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'address_id=' + addressId
                })
                .then(response => {
                    console.log('Delete address response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Delete address response data:', data);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting address');
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    alert('Error deleting address: ' + error.message);
                });
            }
        }

        function submitAddress(event) {
            event.preventDefault();
            
            console.log('Submitting address form');
            if (!validateAddressForm()) {
                console.log('Client-side validation failed');
                document.getElementById('addressErrorMessage').textContent = 'Please correct the errors in the form.';
                document.getElementById('addressErrorMessage').classList.remove('hidden');
                document.getElementById('addressSuccessMessage').classList.add('hidden');
                return;
            }

            const formData = new FormData(document.getElementById('addressForm'));
            formData.append('user_id', <?php echo $user_id; ?>);
            const data = new URLSearchParams();
            for (let [key, value] of formData.entries()) {
                data.append(key, value);
            }
            console.log('Form data:', data.toString());

            fetch('?action=save_address', {
                method: 'POST',
                body: data
            })
            .then(response => {
                console.log('Save address response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Network response was not ok: ' + response.statusText + ' (Response: ' + text + ')');
                    });
                }
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error('Expected JSON response, got: ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Save address response data:', data);
                if (data.success) {
                    document.getElementById('addressSuccessMessage').textContent = 'Address saved successfully!';
                    document.getElementById('addressSuccessMessage').classList.remove('hidden');
                    document.getElementById('addressErrorMessage').classList.add('hidden');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    document.getElementById('addressErrorMessage').textContent = data.message || 'Error saving address';
                    document.getElementById('addressErrorMessage').classList.remove('hidden');
                    document.getElementById('addressSuccessMessage').classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Save address error:', error);
                document.getElementById('addressErrorMessage').textContent = 'An error occurred: ' + error.message;
                document.getElementById('addressErrorMessage').classList.remove('hidden');
                document.getElementById('addressSuccessMessage').classList.add('hidden');
            });
        }

        // Live validation for address form
        const fields = {
            address_full_name: {
                regex: /^[A-Za-z\s]{2,50}$/,
                error: 'Full name must be 2-50 characters, letters and spaces only.',
                required: true
            },
            address_phone_number: {
                regex: /^[6-9]\d{9}$/,
                error: 'Phone number must be a 10-digit number starting with 6-9.',
                required: true
            },
            address_line1: {
                regex: /^.{2,100}$/,
                error: 'Address Line 1 must be 2-100 characters.',
                required: true
            },
            address_line2: {
                regex: /^.{0,100}$/,
                error: 'Address Line 2 must be 0-100 characters.',
                required: false
            },
            landmark: {
                regex: /^.{0,100}$/,
                error: 'Landmark must be 0-100 characters.',
                required: false
            },
            city: {
                regex: /^[A-Za-z\s]{2,50}$/,
                error: 'City must be 2-50 characters, letters and spaces only.',
                required: true
            },
            state: {
                regex: /^[A-Za-z\s]{2,50}$/,
                error: 'State must be 2-50 characters, letters and spaces only.',
                required: true
            },
            postal_code: {
                regex: /^\d{6}$/,
                error: 'Postal code must be a 6-digit number.',
                required: true
            },
            address_type: {
                regex: /^(home|work|other)$/,
                error: 'Please select a valid address type.',
                required: true
            }
        };

        function validateField(fieldId) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(`${fieldId}_error`);
            const config = fields[fieldId];

            // Skip if field or config is missing
            if (!field || !config) {
                console.warn(`Field or config missing for ID: ${fieldId}`);
                return false;
            }

            const value = field.value.trim();

            if (config.required && !value) {
                field.classList.add('invalid');
                if (errorDiv) {
                    errorDiv.textContent = `${fieldId.replace('address_', '').replace('_', ' ').replace(/^\w/, c => c.toUpperCase())} is required.`;
                    errorDiv.classList.remove('hidden');
                }
                return false;
            }

            if (value && !config.regex.test(value)) {
                field.classList.add('invalid');
                if (errorDiv) {
                    errorDiv.textContent = config.error;
                    errorDiv.classList.remove('hidden');
                }
                return false;
            }

            field.classList.remove('invalid');
            if (errorDiv) {
                errorDiv.classList.add('hidden');
            }
            return true;
        }

        function validateAddressForm() {
            let isValid = true;
            Object.keys(fields).forEach(fieldId => {
                if (!validateField(fieldId)) {
                    isValid = false;
                }
            });
            return isValid;
        }

        function clearAddressErrors() {
            Object.keys(fields).forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const errorDiv = document.getElementById(`${fieldId}_error`);
                if (field) {
                    field.classList.remove('invalid');
                }
                if (errorDiv) {
                    errorDiv.classList.add('hidden');
                }
            });
            document.getElementById('addressErrorMessage').classList.add('hidden');
        }

        // Attach input event listeners only to fields defined in the fields object
        Object.keys(fields).forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', () => {
                    validateField(fieldId);
                });
            } else {
                console.warn(`Field not found for ID: ${fieldId}`);
            }
        });

        // Close modals when clicking outside
        document.getElementById('profileModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditProfileModal();
            }
        });

        document.getElementById('addressModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddressModal();
            }
        });
    </script>
</body>
</html>