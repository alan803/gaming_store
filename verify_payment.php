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

$log_path = __DIR__ . '/razorpay_debug.log';
function log_debug($msg) {
    global $log_path;
    file_put_contents($log_path, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature) {
    log_debug("Missing params: " . json_encode($_POST));
    echo json_encode(['success' => false, 'message' => 'Invalid request: missing parameters']);
    exit;
}

// Test keys (replace with env in production)
$key_id = 'rzp_test_enBJVcajFSH1Ci';
$key_secret = '335hWwGIo6uyV9PYp8kXWMej';

// Verify signature
$generated_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $key_secret);
if (!hash_equals($generated_signature, $razorpay_signature)) {
    log_debug("Signature mismatch. Generated: $generated_signature Received: $razorpay_signature");
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
    log_debug("Razorpay payment fetch failed: status={$http_status} err={$curl_err} resp=" . substr((string)$resp,0,400));
    echo json_encode(['success' => false, 'message' => 'Failed to verify payment with Razorpay (server error)']);
    exit;
}

$payment_data = json_decode($resp, true);
if (!is_array($payment_data)) {
    log_debug("Invalid payment_data JSON: {$resp}");
    echo json_encode(['success' => false, 'message' => 'Invalid response from Razorpay']);
    exit;
}

if (($payment_data['status'] ?? '') !== 'captured') {
    log_debug("Payment not captured: " . json_encode($payment_data));
    echo json_encode(['success' => false, 'message' => 'Payment not captured']);
    exit;
}

// Recalculate subtotal from DB for safety
$subtotal = 0.0;
$stmt = $conn->prepare("
    SELECT c.price, COUNT(c.product_id) AS quantity
    FROM cart c
    WHERE c.user_id = ?
    GROUP BY c.product_id, c.price
");
if (!$stmt) {
    log_debug("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error (db prepare)']);
    exit;
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $subtotal += floatval($r['price']) * (int)$r['quantity'];
}
$stmt->close();

// Insert order (adjusted to your orders table: order_id, user_id, total_amount, payment_status, order_status, created_at)
$amount = (float) $subtotal;
$insert = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_status, order_status, created_at) VALUES (?, ?, ?, ?, NOW())");
if (!$insert) {
    log_debug("Prepare insert failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error (db prepare insert)']);
    exit;
}
$payment_status = 'paid';
$order_status = 'processing';
$insert->bind_param("idss", $user_id, $amount, $payment_status, $order_status);
$ok = $insert->execute();
$order_db_id = $insert->insert_id;
if (!$ok) {
    log_debug("Insert order failed: " . $insert->error);
    echo json_encode(['success' => false, 'message' => 'Server error (db insert)']);
    exit;
}
$insert->close();

// Optional: record order items here (if you have order_items table)

// Clear cart
$del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
if ($del) {
    $del->bind_param("i", $user_id);
    $del->execute();
    $del->close();
} else {
    log_debug("Failed to prepare cart delete: " . $conn->error);
}

log_debug("Payment verified and order recorded. order_db_id={$order_db_id} razorpay_payment_id={$razorpay_payment_id}");
echo json_encode(['success' => true, 'order_db_id' => $order_db_id, 'redirect' => 'orders.php']);
exit;