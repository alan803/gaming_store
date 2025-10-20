<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'connection.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'cart_count' => 0,
    'debug' => []
];

// Check if user is logged in
if (!isset($_SESSION['auth_user_id'])) {
    $response['message'] = 'Please login to add items to cart';
    // If this is an AJAX request, return JSON, otherwise redirect to login
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode($response);
    } else {
        $_SESSION['login_redirect'] = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: login.php');
    }
    exit();
}

// Get request data (works for both GET and POST)
$request = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$product_id = isset($request['product_id']) ? intval($request['product_id']) : 0;

// Add debug info
$response['debug'] = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'product_id' => $product_id,
    'user_id' => $_SESSION['auth_user_id'] ?? null,
    'session' => $_SESSION
];

// Check if product_id is provided
if ($product_id > 0) {
    $user_id = $_SESSION['auth_user_id'];
    
    // Check if product exists and get its price
    $product_query = "SELECT price, stock FROM products WHERE id = ?";
    $stmt = $conn->prepare($product_query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();
    
    if ($product_result->num_rows === 0) {
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit();
    }
    
    $product = $product_result->fetch_assoc();
    $price = $product['price'];
    $stock = $product['stock'];
    
    // Check if there's enough stock
    if ($stock <= 0) {
        $response['message'] = 'Product out of stock';
        echo json_encode($response);
        exit();
    }
    
    // Check if product is already in cart
    $check_query = "SELECT cart_id FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Product already in cart';
    } else {
        // Add new item to cart
        $insert_query = "INSERT INTO cart (user_id, product_id, price) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iid", $user_id, $product_id, $price);
        $success = $stmt->execute();
        $message = $success ? 'Product added to cart successfully' : 'Failed to add to cart: ' . $conn->error;
        
        $response['success'] = $success;
        $response['message'] = $message;
    }
    
    if ($response['success'] || $cart_result->num_rows > 0) {
        // Get updated cart count for the user (count unique products)
        $count_query = "SELECT COUNT(*) as total_items FROM cart WHERE user_id = ?";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count_result = $stmt->get_result();
        $cart_count = $count_result->fetch_assoc()['total_items'] ?? 0;
        
        $response['cart_count'] = intval($cart_count);
        
        // Set success message in session for page reload
        $_SESSION['cart_message'] = [
            'type' => 'success',
            'text' => $response['message']
        ];
    }
} else {
    $response['message'] = 'Invalid request';
}

// Return JSON response
echo json_encode($response);

// Close connection
$conn->close();
?>