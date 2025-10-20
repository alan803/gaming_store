<?php
session_start();
include 'connection.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['auth_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = (int) $_SESSION['auth_user_id'];

$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? null;
$razorpay_order_id   = $_POST['razorpay_order_id'] ?? null;
$razorpay_signature  = $_POST['razorpay_signature'] ?? null;
$product_id          = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$quantity            = isset($_POST['quantity']) ? max(1, (int) $_POST['quantity']) : 1;

$log_path = __DIR__ . '/razorpay_debug.log';
function log_single($msg) {
    global $log_path;
    file_put_contents($log_path, date('[Y-m-d H:i:s] ') . '[SINGLE] ' . $msg . PHP_EOL, FILE_APPEND);
}

if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature || $product_id <= 0) {
    log_single('Missing params: ' . json_encode($_POST));
    echo json_encode(['success' => false, 'message' => 'Invalid request: missing parameters']);
    exit;
}

// Test keys
$key_id = 'rzp_test_enBJVcajFSH1Ci';
$key_secret = '335hWwGIo6uyV9PYp8kXWMej';

// Verify signature
$generated_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $key_secret);
if (!hash_equals($generated_signature, $razorpay_signature)) {
    log_single("Signature mismatch. Generated: $generated_signature Received: $razorpay_signature");
    echo json_encode(['success' => false, 'message' => 'Signature verification failed']);
    exit;
}

// Verify payment details with Razorpay server-side
$ch = curl_init("https://api.razorpay.com/v1/payments/{$razorpay_payment_id}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $key_id . ":" . $key_secret);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$resp = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if (!$resp || $http_status < 200 || $http_status >= 300) {
    log_single("Payment fetch failed: status={$http_status} err={$curl_err} resp=" . substr((string)$resp,0,400));
    echo json_encode(['success' => false, 'message' => 'Failed to verify payment with Razorpay (server error)']);
    exit;
}

$payment_data = json_decode($resp, true);
if (!is_array($payment_data)) {
    log_single('Invalid payment_data JSON');
    echo json_encode(['success' => false, 'message' => 'Invalid response from Razorpay']);
    exit;
}

if (($payment_data['status'] ?? '') !== 'captured') {
    log_single('Payment not captured: ' . json_encode($payment_data));
    echo json_encode(['success' => false, 'message' => 'Payment not captured']);
    exit;
}

// Get product price fresh from DB and stock check
$stmt = $conn->prepare('SELECT price, stock FROM products WHERE id = ? LIMIT 1');
if (!$stmt) {
    log_single('DB prepare failed: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
$stmt->bind_param('i', $product_id);
$stmt->execute();
$res = $stmt->get_result();
$prod = $res->fetch_assoc();
$stmt->close();

if (!$prod) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$quantity = max(1, min($quantity, (int)$prod['stock']));
$amount = (float)$prod['price'] * $quantity;

// Insert order row
$insert = $conn->prepare('INSERT INTO orders (user_id, total_amount, payment_status, order_status, created_at) VALUES (?, ?, ?, ?, NOW())');
if (!$insert) {
    log_single('Prepare insert failed: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error (db insert)']);
    exit;
}
$payment_status = 'paid';
$order_status = 'processing';
$insert->bind_param('idss', $user_id, $amount, $payment_status, $order_status);
$ok = $insert->execute();
$order_db_id = $insert->insert_id;
$insert->close();

if (!$ok || !$order_db_id) {
    echo json_encode(['success' => false, 'message' => 'Failed to create order']);
    exit;
}

// Optional: decrement stock
$upd = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
if ($upd) {
    $upd->bind_param('iii', $quantity, $product_id, $quantity);
    $upd->execute();
    $upd->close();
}

// Optional: insert into order_items if such a table exists
// Skipped here since schema not provided.

log_single("Single item order created id={$order_db_id} product_id={$product_id} qty={$quantity}");
echo json_encode(['success' => true, 'order_db_id' => $order_db_id, 'redirect' => 'orders.php']);
exit;
?>


