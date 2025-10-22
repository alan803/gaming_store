<?php
// Start session and include DB connection
session_start();
include 'connection.php'; // make sure this sets $conn properly

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = trim($_POST['identifier']); // username or email
    $password = $_POST['password'];

    if (empty($identifier) || empty($password)) {
        $error = "All fields are required.";
    } else {
        $query = "SELECT login.user_id, login.email, login.password_hash, users.username, users.role 
                  FROM login 
                  INNER JOIN users ON login.user_id = users.user_id 
                  WHERE login.email = ? OR users.username = ? 
                  LIMIT 1";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['password_hash'])) {
                // Store user session data with specific names
                $_SESSION['auth_user_id'] = $row['user_id'];
                $_SESSION['auth_username'] = $row['username'];
                $_SESSION['auth_role'] = $row['role'];

                // Role-based handling
                if ($row['role'] === 'admin') {
                    $_SESSION['auth_admin_id'] = $row['user_id'];
                    header("Location: admindashboard.php");
                } else {
                    // For regular users, store their cart ID if needed
                    $_SESSION['auth_customer_id'] = $row['user_id'];
                    header("Location: homepage.php");
                }
                exit();
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "Invalid credentials.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #ffffff; color: #333333; }
        .neon-text { text-shadow: 0 0 10px rgba(99, 102, 241, 0.2); }
        .btn-primary { background: linear-gradient(45deg, #4f46e5, #6366f1); color: white; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.2); }
        .nav-link { position: relative; color: #4b5563; }
        .nav-link:after { content: ''; position: absolute; width: 0; height: 2px; bottom: -2px; left: 0; background: #4f46e5; transition: width 0.3s ease; }
        .nav-link:hover { color: #1f2937; }
        .nav-link:hover:after { width: 100%; }
        nav { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background-color: white !important; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        section:first-of-type { padding-top: 6rem; }
        h1, h2, h3, h4, h5, h6 { color: #111827; }
        section { background-color: #ffffff; }
        input { background-color: #ffffff !important; border: 1px solid #d1d5db !important; transition: all 0.3s ease; }
        input:focus { border-color: #6366f1 !important; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .error { color: #ef4444; font-size: 0.875rem; margin-top: 0.25rem; }
        .form-container { background: #ffffff; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .form-container:hover { transform: translateY(-5px); border-color: #6366f1; box-shadow: 0 10px 25px -5px rgba(99,102,241,0.1); }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white shadow-sm">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-gamepad text-white"></i>
                    </div>
                    <span class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">NexusGear</span>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="homepage.php" class="nav-link text-gray-600 hover:text-indigo-600">Home</a>
                    <a href="homepage.php" class="nav-link text-gray-600 hover:text-indigo-600">About</a>
                    <a href="products.php" class="nav-link text-gray-600 hover:text-indigo-600">Shop</a>
                </div>
                <div class="flex items-center space-x-6">
                    <button class="text-gray-600 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    <button class="text-gray-600 hover:text-indigo-600 relative transition-colors">
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span class="absolute -top-2 -right-2 bg-indigo-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">0</span>
                    </button>
                    <button class="md:hidden text-gray-600 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>
    <!-- Login Form Section -->
    <section class="min-h-screen flex items-center justify-center pt-24 pb-8 bg-gradient-to-b from-white to-gray-50">
        <div class="form-container max-w-sm w-full rounded-xl p-4 shadow-xl bg-white">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold mb-2 neon-text">Log In</h2>
                <p class="text-gray-600">Welcome back! Enter your email or username and password.</p>
            </div>
            <?php if ($error): ?>
                <div class="error text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-6">
                <div class="form-group">
                    <input type="text" name="identifier" id="identifier" placeholder="Email or Username" required autofocus class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 placeholder-gray-400">
                </div>
                <div class="form-group">
                    <input type="password" name="password" id="password" placeholder="Password" required class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 placeholder-gray-400">
                </div>
                <button type="submit" class="btn-primary w-full py-3 rounded-lg font-medium text-white hover:shadow-lg transition-all">Log In</button>
                <div class="text-center mt-2">
                    <a href="forgot_password.php" class="text-indigo-600 hover:text-indigo-800 text-sm transition-colors">Forgot password?</a>
                </div>
            </form>
            <p class="text-center text-gray-600 mt-6">
                Don't have an account? <a href="signup.php" class="text-indigo-600 hover:text-indigo-800 transition-colors">Sign Up</a>
            </p>
        </div>
    </section>
</body>
</html>
