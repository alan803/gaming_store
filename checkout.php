<?php
session_start();
include 'connection.php';

// Ensure user is logged in
if (!isset($_SESSION['auth_user_id'])) {
    $_SESSION['login_redirect'] = 'checkout.php';
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['auth_user_id'];

// Fetch cart items and subtotal (same logic as cart.php)
$cart_items = [];
$subtotal = 0;
$cart_query = "
    SELECT c.product_id, c.price, p.name, p.image_name, p.stock,
           COUNT(c.product_id) as quantity
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
    GROUP BY c.product_id, c.price, p.name, p.image_name, p.stock
";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $subtotal += $row['price'] * $row['quantity'];
    }
}
$stmt->close();

if ($subtotal <= 0) {
    header('Location: cart.php');
    exit();
}

// RAZORPAY KEYS (test) - replace with env variables in production
$key_id = 'rzp_test_enBJVcajFSH1Ci';
$key_secret = '335hWwGIo6uyV9PYp8kXWMej';

// Prepare order on Razorpay (amount in paise)
$amount_paise = intval(round($subtotal * 100));
$order_payload = [
    'amount' => $amount_paise,
    'currency' => 'INR',
    'receipt' => 'rcpt_' . time() . '_' . $user_id,
    'payment_capture' => 1
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $key_id . ":" . $key_secret);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

$razorpay_order_id = null;
$order_error = null;
if ($response && $http_status >= 200 && $http_status < 300) {
    $order_data = json_decode($response, true);
    if (isset($order_data['id'])) {
        $razorpay_order_id = $order_data['id'];
    } else {
        $order_error = 'Invalid order response from Razorpay';
    }
} else {
    $order_error = 'Razorpay order creation failed: ' . ($curl_err ?: $response);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        :root { --primary:#4f46e5; --primary-light:#6366f1; --dark:#111827; --light:#f9fafb; --gray:#6b7280; --gray-light:#e5e7eb; }
        html, body { width:100%; overflow-x:hidden; margin:0; padding:0; scroll-behavior:smooth; }
        body { font-family:'Poppins', sans-serif; background-color:#ffffff; color:#333333; line-height:1.7; font-size:1.05rem; -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale; }
        .gradient-bg { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); }
        .neon-text { text-shadow: 0 0 10px rgba(99,102,241,.2); }
        .nav-link { position:relative; color:#4b5563; font-size:1.05rem; font-weight:500; padding:.5rem 0; }
        .nav-link:after { content:''; position:absolute; width:0; height:2px; bottom:-2px; left:0; background:#4f46e5; transition: width .3s ease; }
        .nav-link:hover { color:#1f2937; }
        .nav-link:hover:after { width:100%; }
        nav { position:fixed; top:0; left:0; right:0; z-index:50; background-color:#fff !important; box-shadow:0 2px 8px rgba(0,0,0,.08); backdrop-filter: blur(10px); }
        .logo-text { font-size:1.5rem; font-weight:700; }
        .logo-icon { width:2.5rem; height:2.5rem; font-size:1.25rem; }
        .cart-badge { width:1.25rem; height:1.25rem; font-size:.7rem; top:-.5rem; right:-.5rem; }
        .container { width:100%; max-width:1280px; margin:0 auto; padding:0 2rem; box-sizing:border-box; }
        @media (max-width: 768px) { .container { padding:0 1.25rem; } }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .card:hover { box-shadow:0 15px 30px -5px rgba(79,70,229,.15); border-color:var(--primary); }
        .btn-primary { background:linear-gradient(45deg, var(--primary), var(--primary-light)); color:#fff; font-weight:500; padding:.75rem 2rem; border-radius:8px; transition:all .3s cubic-bezier(.4,0,.2,1); position:relative; overflow:hidden; z-index:1; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow:0 10px 20px -5px rgba(79,70,229,.3); }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white shadow-sm">
        <div class="container mx-auto px-4 py-5">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="logo-icon bg-indigo-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-gamepad text-white"></i>
                    </div>
                    <span class="logo-text bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">NexusGear</span>
                </div>
                <div class="hidden md:flex space-x-10">
                    <a href="homepage.php" class="nav-link text-gray-600 hover:text-indigo-600">Home</a>
                    <a href="homepage.php#about" class="nav-link text-gray-600 hover:text-indigo-600">About</a>
                    <a href="products.php" class="nav-link text-gray-600 hover:text-indigo-600">Shop</a>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="cart.php" class="text-gray-600 hover:text-indigo-600 relative transition-colors">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span class="cart-badge absolute -top-2 -right-2 bg-indigo-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </a>
                    <?php if (isset($_SESSION['auth_user_id'])): ?>
                        <div class="relative group">
                            <button id="profileDropdownButton" class="flex items-center space-x-2 text-gray-700 hover:text-indigo-600 transition-colors" aria-expanded="false">
                                <span class="hidden md:inline font-medium"><?php echo htmlspecialchars($_SESSION['auth_username']); ?></span>
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                                    <i class="fas fa-user text-sm"></i>
                                </div>
                            </button>
                            <div id="profileDropdown" class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-2 z-50 hidden border border-gray-100">
                                <a href="user_profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
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
                            <a href="login.php" class="text-gray-600 hover:text-indigo-600 transition-colors text-sm font-medium">Sign In</a>
                            <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Sign Up</a>
                        </div>
                    <?php endif; ?>
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
                    <a href="homepage.php#about" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">About</a>
                    <a href="products.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium text-base border-b border-gray-100">Products</a>
                    <?php if (isset($_SESSION['auth_user_id'])): ?>
                        <div class="mt-6 pt-6 border-t-2 border-gray-200">
                            <p class="text-sm text-gray-500 mb-4">Account</p>
                            <a href="user_profile.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium"><i class="fas fa-user mr-2"></i>Profile</a>
                            <a href="orders.php" class="block py-3 text-gray-600 hover:text-indigo-600 font-medium"><i class="fas fa-shopping-bag mr-2"></i>My Orders</a>
                            <form action="logout.php" method="post" class="w-full mt-2">
                                <button type="submit" class="w-full text-left py-3 text-red-600 hover:text-red-700 font-medium"><i class="fas fa-sign-out-alt mr-2"></i>Logout</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="mt-6 pt-6 border-t-2 border-gray-200 space-y-3">
                            <a href="login.php" class="block py-3 text-center text-gray-600 hover:text-indigo-600 font-medium border border-gray-300 rounded-lg">Login</a>
                            <a href="signup.php" class="block py-3 text-center bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Checkout Section -->
    <section class="relative pt-32 pb-24 overflow-hidden gradient-bg">
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center mb-10">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">
                    <span class="text-gray-900">Secure</span>
                    <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Checkout</span>
                </h1>
                <p class="text-gray-600">Review your order and complete the payment</p>
            </div>

            <?php if ($order_error): ?>
                <div class="card p-6 max-w-2xl mx-auto text-red-700 bg-red-50 border-red-200">
                    <?php echo htmlspecialchars($order_error); ?>
                    <p class="mt-4"><a class="text-indigo-600" href="cart.php">Back to cart</a></p>
                    <?php exit(); ?>
                </div>
            <?php else: ?>
                <div class="max-w-lg mx-auto">
                    <div class="card p-6">
                        <h3 class="font-semibold mb-4">Payment</h3>
                        <div class="flex justify-between text-gray-600 mb-2">
                            <span>Total</span>
                            <span class="font-bold text-gray-900">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <p class="text-sm text-gray-500 mb-4">You will be redirected to Razorpay to complete payment (Test mode).</p>
                        <button id="rzpPayBtn" class="btn-primary w-full">Pay $<?php echo number_format($subtotal, 2); ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    

    <!-- Footer -->
    <footer class="bg-gray-50 text-gray-600 border-t border-gray-100">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-10">
                <div class="md:col-span-2">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-gamepad text-white"></i>
                        </div>
                        <span class="text-xl font-bold bg-gradient-to-r from-indigo-400 to-purple-500 bg-clip-text text-transparent">NexusGear</span>
                    </div>
                    <p class="text-gray-500 mb-6">Your ultimate destination for premium gaming accessories.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-indigo-600 flex items-center justify-center text-gray-600 hover:text-white transition-colors"><i class="fab fa-discord"></i></a>
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
                        <li><a href="homepage.php#about" class="text-gray-600 hover:text-gray-900 transition-colors">About Us</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-100 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm mb-4 md:mb-0">© <?php echo date('Y'); ?> NexusGear. All rights reserved.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-gray-600 text-sm transition-colors">Privacy Policy</a>
                    <a href="#" class="text-gray-400 hover:text-gray-600 text-sm transition-colors">Terms of Service</a>
                    <a href="#" class="text-gray-400 hover:text-gray-600 text-sm transition-colors">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
    (function(){
        const payBtn = document.getElementById('rzpPayBtn');
        payBtn.addEventListener('click', function (e) {
            e.preventDefault();

            const options = {
                "key": "<?php echo htmlspecialchars($key_id); ?>",
                "amount": "<?php echo $amount_paise; ?>",
                "currency": "INR",
                "name": "NexusGear",
                "description": "Order Payment",
                "order_id": "<?php echo htmlspecialchars($razorpay_order_id); ?>",
                "handler": function (response){
                    // Send details to server for verification & order fulfillment
                    const body = `razorpay_payment_id=${encodeURIComponent(response.razorpay_payment_id)}&razorpay_order_id=${encodeURIComponent(response.razorpay_order_id)}&razorpay_signature=${encodeURIComponent(response.razorpay_signature)}`;
                    fetch('verify_payment.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: body
                    })
                    .then(async r => {
                        const text = await r.text();
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            data = { success: false, message: text || 'Invalid JSON response from server' };
                        }
                        if (r.ok && data.success) {
                            window.location = data.redirect ?? 'orders.php';
                        } else {
                            console.error('verify_payment response', r.status, data);
                            alert('Payment verification failed: ' + (data.message || ('HTTP ' + r.status)));
                        }
                    })
                    .catch(err => {
                        console.error('verify_payment network error', err);
                        alert('Server error while verifying payment: ' + (err.message || err));
                    });
                },
                "prefill": {
                    "name": "<?php echo isset($_SESSION['auth_username']) ? addslashes($_SESSION['auth_username']) : ''; ?>",
                    "email": "<?php echo isset($_SESSION['auth_user_email']) ? addslashes($_SESSION['auth_user_email']) : ''; ?>"
                },
                "theme": {
                    "color": "#4f46e5"
                }
            };
            const rzp = new Razorpay(options);
            rzp.open();
        });
    })();
    // Profile dropdown & mobile menu interactions
    document.addEventListener('DOMContentLoaded', function() {
        const profileDropdownButton = document.getElementById('profileDropdownButton');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdownButton && profileDropdown) {
            profileDropdownButton.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                const isExpanded = profileDropdownButton.getAttribute('aria-expanded') === 'true';
                profileDropdownButton.setAttribute('aria-expanded', !isExpanded);
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', function(e) {
                if (!profileDropdownButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                    profileDropdownButton.setAttribute('aria-expanded', 'false');
                }
            });
            profileDropdown.querySelectorAll('a, button').forEach(item => {
                item.addEventListener('click', function() {
                    profileDropdown.classList.add('hidden');
                    profileDropdownButton.setAttribute('aria-expanded', 'false');
                });
            });
        }
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMobileMenuButton = document.getElementById('closeMobileMenu');
        if (mobileMenuButton && mobileMenu && closeMobileMenuButton) {
            mobileMenuButton.addEventListener('click', function(e) {
                e.preventDefault();
                mobileMenu.classList.toggle('hidden');
                mobileMenuButton.setAttribute('aria-expanded', mobileMenu.classList.contains('hidden') ? 'false' : 'true');
            });
            closeMobileMenuButton.addEventListener('click', function(e) {
                e.preventDefault();
                mobileMenu.classList.add('hidden');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
            });
            mobileMenu.querySelectorAll('a, button').forEach(item => {
                item.addEventListener('click', function() {
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                });
            });
            document.addEventListener('click', function(e) {
                if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.add('hidden');
                    mobileMenuButton.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
    </script>
</body>
</html>