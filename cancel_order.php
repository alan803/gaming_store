<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/connection.php';

if (!isset($_SESSION['auth_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = (int) $_SESSION['auth_user_id'];

// Basic method guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$orderId = isset($_POST['order_id']) ? trim($_POST['order_id']) : '';
if ($orderId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'order_id is required']);
    exit;
}

// 1) Verify the order belongs to the current user and is cancellable
$stmt = $conn->prepare("SELECT order_id, payment_status, order_status FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    exit;
}
$stmt->bind_param('si', $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$currentStatus = strtolower((string) $order['order_status']);
$paymentStatus = strtolower((string) $order['payment_status']);

// Define cancellable statuses (adjust if needed)
$cancellableStatuses = ['processing', 'pending'];
if (!in_array($currentStatus, $cancellableStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled in its current status']);
    exit;
}

// 2) Update order status to Cancelled
$newStatus = 'cancelled';
$update = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ? AND user_id = ? LIMIT 1");
if (!$update) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare update']);
    exit;
}
$update->bind_param('ssi', $newStatus, $orderId, $userId);
$ok = $update->execute();
$affected = $update->affected_rows;
$update->close();

if (!$ok || $affected < 1) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
    exit;
}

// 3) Optional: handle refunds if needed
// If you use online payments, trigger a refund workflow here when $paymentStatus === 'paid'.
// For now, we only mark the order as cancelled.

echo json_encode(['success' => true, 'message' => 'Order cancelled']);
exit;
?>


