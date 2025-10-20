<?php
session_start();
include 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['auth_user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['auth_user_id'];

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['product_id']) || !isset($_POST['action']) || $_POST['action'] !== 'remove') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$product_id = intval($_POST['product_id']);

// Start transaction for consistency
$conn->begin_transaction();

try {
    // Delete the product from the cart
    $delete_query = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($delete_query);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $user_id, $product_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    // Fetch updated cart count
    $cart_count_query = "SELECT COUNT(*) as total FROM cart WHERE user_id = ?";
    $stmt_count = $conn->prepare($cart_count_query);
    if ($stmt_count === false) {
        throw new Exception("Prepare count failed: " . $conn->error);
    }
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $cart_count = $result_count->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();

    // Fetch updated subtotal
    $subtotal_query = "
        SELECT COALESCE(SUM(c.price * (SELECT COUNT(*) FROM cart c2 WHERE c2.product_id = c.product_id AND c2.user_id = c.user_id)), 0) as subtotal
        FROM cart c 
        WHERE c.user_id = ?
    ";
    $stmt_subtotal = $conn->prepare($subtotal_query);
    if ($stmt_subtotal === false) {
        throw new Exception("Prepare subtotal failed: " . $conn->error);
    }
    $stmt_subtotal->bind_param("i", $user_id);
    $stmt_subtotal->execute();
    $result_subtotal = $stmt_subtotal->get_result();
    $subtotal = $result_subtotal->fetch_assoc()['subtotal'] ?? 0;
    $stmt_subtotal->close();

    // Commit transaction
    $conn->commit();

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'cart_count' => $cart_count,
        'subtotal' => $subtotal
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error in update_cart.php: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting item: ' . $e->getMessage()]);
}

$conn->close();
?>